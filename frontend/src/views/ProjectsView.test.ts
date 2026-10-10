import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { setUser } from '../session'
import ProjectsView from './ProjectsView.vue'

function projects(n: number) {
  return Array.from({ length: n }, (_, i) => ({
    id: `id-${i}`,
    name: `Projekt ${i}`,
    createdAt: '2026-10-10T12:00:00+00:00',
  }))
}

async function render(permissions: string[], list: unknown[]) {
  setUser({ id: '1', email: 'jane@example.com', roles: ['user'], permissions, createdAt: '2026-10-10T12:00:00+00:00' })
  vi.stubGlobal(
    'fetch',
    vi.fn().mockImplementation(async () => new Response(JSON.stringify(list), { status: 200 })),
  )
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
  const wrapper = mount(ProjectsView, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

afterEach(() => vi.unstubAllGlobals())

describe('ProjectsView', () => {
  it.each([
    [1, '1 projekt'],
    [3, '3 projekty'],
    [5, '5 projektów'],
    [12, '12 projektów'],
    [22, '22 projekty'],
  ])('pluralises the count for %i projects', async (n, label) => {
    const wrapper = await render(['project.view'], projects(n))

    expect(wrapper.find('.page-head p').text()).toBe(label)
    expect(wrapper.findAll('.row')).toHaveLength(n)
  })

  it('hides create, rename and delete without project.manage', async () => {
    const wrapper = await render(['project.view'], projects(1))

    expect(wrapper.find('#new-project').exists()).toBe(false)
    expect(wrapper.find('.row-actions').exists()).toBe(false)
  })

  it('shows them with project.manage', async () => {
    const wrapper = await render(['project.view', 'project.manage'], projects(1))

    expect(wrapper.find('#new-project').exists()).toBe(true)
    expect(wrapper.find('.row-actions').text()).toContain('Usuń')
  })

  it('explains a missing project.view permission', async () => {
    const wrapper = await render([], [])

    expect(wrapper.find('[role="alert"]').text()).toBe('Brak uprawnień do przeglądania projektów.')
  })
})
