import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { setUser } from '../session'
import SourcesView from './SourcesView.vue'

const PROJECT = '00000000-0000-7000-a000-000000000001'
const source = (over: Record<string, unknown> = {}) => ({
  id: 's1',
  projectId: PROJECT,
  name: 'API',
  tokenPrefix: 'wd_Ab3xY',
  createdAt: '2026-10-10T12:00:00+00:00',
  revokedAt: null,
  ...over,
})

// jsdom has no modal dialogs; the component only needs open/close and the close event.
beforeAll(() => {
  HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement) {
    this.open = true
  }
  HTMLDialogElement.prototype.close = function (this: HTMLDialogElement, value?: string) {
    if (value !== undefined) this.returnValue = value
    this.open = false
    this.dispatchEvent(new Event('close'))
  }
})

afterEach(() => vi.unstubAllGlobals())

function respond(routes: Record<string, unknown>) {
  const fetch = vi.fn(async (url: string, init?: RequestInit) => {
    const key = `${init?.method ?? 'GET'} ${url}`
    const body = routes[key]
    if (body === undefined) return new Response(null, { status: 404 })
    return new Response(JSON.stringify(body), { status: key.startsWith('POST') ? 201 : 200 })
  })
  vi.stubGlobal('fetch', fetch)
  return fetch
}

async function render(permissions: string[]) {
  setUser({ id: '1', email: 'jane@example.com', roles: ['user'], permissions, createdAt: '2026-10-10T12:00:00+00:00' })
  const wrapper = mount(SourcesView, { props: { id: PROJECT }, attachTo: document.body })
  await flushPromises()
  return wrapper
}

describe('SourcesView', () => {
  it('shows the token once after creating a source and forgets it on close', async () => {
    respond({
      [`GET /api/projects/${PROJECT}/sources`]: [],
      [`POST /api/projects/${PROJECT}/sources`]: { source: source(), token: 'wd_secret-token-value' },
    })
    const wrapper = await render(['source.manage'])

    await wrapper.find('#new-source').setValue('API')
    await wrapper.find('form.panel-head').trigger('submit')
    await flushPromises()

    const dialog = wrapper.find('dialog.token-dialog')
    expect((dialog.element as HTMLDialogElement).open).toBe(true)
    expect(dialog.text()).toContain('wd_secret-token-value')
    expect(dialog.find('pre').text()).toContain('Authorization: Bearer wd_secret-token-value')
    expect(wrapper.findAll('.row')).toHaveLength(1)

    ;(dialog.element as HTMLDialogElement).close()
    await flushPromises()

    expect(wrapper.html()).not.toContain('wd_secret-token-value')
    wrapper.unmount()
  })

  it('lists sources by prefix only and hides actions for revoked ones', async () => {
    respond({
      [`GET /api/projects/${PROJECT}/sources`]: [
        source(),
        source({ id: 's2', name: 'Worker', revokedAt: '2026-10-11T08:00:00+00:00' }),
      ],
    })
    const wrapper = await render(['source.manage'])

    const rows = wrapper.findAll('.row')
    expect(rows[0]?.text()).toContain('wd_Ab3xY…')
    expect(rows[0]?.text()).toContain('Aktywne')
    expect(rows[0]?.find('.row-actions').exists()).toBe(true)
    expect(rows[1]?.text()).toContain('Odwołane')
    expect(rows[1]?.find('.row-actions').exists()).toBe(false)
    wrapper.unmount()
  })

  it('explains a missing source.manage permission without calling the API', async () => {
    const fetch = respond({})
    const wrapper = await render(['project.view'])

    expect(wrapper.find('[role="alert"]').text()).toBe('Brak uprawnień do zarządzania źródłami.')
    expect(fetch).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
