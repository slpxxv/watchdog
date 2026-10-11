import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { LogLine } from '../api'
import { setUser } from '../session'
import ProjectLogsView from './ProjectLogsView.vue'

const PROJECT = '00000000-0000-7000-a000-000000000001'
const line = (id: number, over: Partial<LogLine> = {}): LogLine => ({
  id,
  timestamp: new Date().toISOString(),
  receivedAt: new Date().toISOString(),
  level: 'info',
  message: `line ${id}`,
  context: [],
  sourceId: 's1',
  sourceName: 'API',
  ...over,
})

type Handler = (url: URL) => unknown

function stubApi(handlers: Record<string, Handler>) {
  const fetch = vi.fn(async (input: string) => {
    const url = new URL(input, 'http://localhost')
    const handler = Object.entries(handlers).find(([path]) => url.pathname === path)?.[1]
    if (!handler) return new Response(null, { status: 404 })
    return new Response(JSON.stringify(handler(url)), { status: 200 })
  })
  vi.stubGlobal('fetch', fetch)
  return fetch
}

async function render(query = '', permissions = ['log.view']) {
  setUser({ id: '1', email: 'jane@example.com', roles: ['user'], permissions, createdAt: '2026-10-11T12:00:00+00:00' })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/projects/:id/logs', name: 'project-logs', component: { template: '<div />' } }],
  })
  await router.push(`/projects/${PROJECT}/logs${query}`)
  const wrapper = mount(ProjectLogsView, { props: { id: PROJECT }, global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

const LOGS = `/api/projects/${PROJECT}/logs`
const TAIL = `/api/projects/${PROJECT}/logs/tail`
const SOURCES = `/api/projects/${PROJECT}/sources`

afterEach(() => {
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

describe('ProjectLogsView', () => {
  it('sends the filters from the URL', async () => {
    const fetch = stubApi({
      [SOURCES]: () => [],
      [LOGS]: () => ({ items: [], nextCursor: null, from: '', to: null }),
    })

    await render('?level=error&q=boom&range=1h&source=s1')

    const search = fetch.mock.calls.map(([u]) => new URL(u, 'http://localhost')).find((u) => u.pathname === LOGS)!
    expect(search.searchParams.get('level')).toBe('error')
    expect(search.searchParams.get('q')).toBe('boom')
    expect(search.searchParams.getAll('sources[]')).toEqual(['s1'])
    const from = new Date(search.searchParams.get('from')!).getTime()
    expect(Date.now() - from).toBeGreaterThan(59 * 60_000)
    expect(Date.now() - from).toBeLessThan(61 * 60_000)
  })

  it('shows lines as plain text and expands one to its context', async () => {
    stubApi({
      [SOURCES]: () => [],
      [LOGS]: () => ({
        items: [line(1, { level: 'error', message: '\x1b[31mPayment failed\x1b[0m', context: { order: 5 } })],
        nextCursor: null,
        from: '',
        to: null,
      }),
    })
    const { wrapper } = await render()

    const row = wrapper.find('.log-row')
    expect(row.text()).toContain('Payment failed')
    expect(row.text()).not.toContain('\x1b')
    expect(wrapper.find('.log-line').classes()).toContain('lvl-error')

    await row.trigger('click')
    expect(wrapper.find('.log-detail pre').text()).toContain('"order": 5')
  })

  it('loads older lines with the cursor and the same time range', async () => {
    const fetch = stubApi({
      [SOURCES]: () => [],
      [LOGS]: (url) =>
        url.searchParams.get('cursor')
          ? { items: [line(1)], nextCursor: null, from: 'x', to: null }
          : { items: [line(3), line(2)], nextCursor: 'abc', from: '2026-10-10T12:00:00+00:00', to: null },
    })
    const { wrapper } = await render()

    await wrapper.find('.terminal-more button').trigger('click')
    await flushPromises()

    expect(wrapper.findAll('.log-row')).toHaveLength(3)
    const older = new URL(fetch.mock.calls.at(-1)![0], 'http://localhost')
    expect(older.searchParams.get('cursor')).toBe('abc')
    expect(older.searchParams.get('from')).toBe('2026-10-10T12:00:00+00:00')
    expect(wrapper.find('.terminal-more').exists()).toBe(false)
  })

  it('explains a missing log.view permission without asking for logs', async () => {
    const fetch = stubApi({})
    const { wrapper } = await render('', ['project.view'])

    expect(wrapper.find('[role="alert"]').text()).toBe('Brak uprawnień do przeglądania logów.')
    expect(fetch).not.toHaveBeenCalled()
  })

  it('polls the tail from the last id and does not repeat lines', async () => {
    vi.useFakeTimers()
    const afters: string[] = []
    let call = 0
    stubApi({
      [SOURCES]: () => [],
      [TAIL]: (url) => {
        afters.push(url.searchParams.get('after')!)
        call++
        // Second answer repeats line 2 (overlap window) and brings line 3.
        return call === 1 ? { items: [line(1), line(2)], lastId: 2 } : { items: [line(2), line(3)], lastId: 3 }
      },
    })
    const { wrapper } = await render('?live=1')

    expect(wrapper.findAll('.log-row').map((r) => r.find('.log-message').text())).toEqual(['line 1', 'line 2'])

    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()

    expect(afters).toEqual(['0', '2'])
    expect(wrapper.findAll('.log-row').map((r) => r.find('.log-message').text())).toEqual([
      'line 1',
      'line 2',
      'line 3',
    ])
    wrapper.unmount()
  })
})
