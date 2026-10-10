import { beforeEach, describe, expect, it, vi } from 'vitest'

const jane = {
  id: '1',
  email: 'jane@example.com',
  roles: ['user'],
  permissions: [],
  createdAt: '2026-10-10T12:00:00+00:00',
}

// session.ts keeps module-level state, so each test gets a fresh copy.
async function freshSession() {
  vi.resetModules()
  return import('./session')
}

beforeEach(() => vi.unstubAllGlobals())

describe('session', () => {
  it('shares one /api/me request between callers', async () => {
    const fetch = vi.fn().mockImplementation(async () => new Response(JSON.stringify(jane), { status: 200 }))
    vi.stubGlobal('fetch', fetch)
    const { loadUser, user } = await freshSession()

    const [a, b] = await Promise.all([loadUser(), loadUser()])

    expect(a).toEqual(jane)
    expect(b).toEqual(jane)
    expect(user.value).toEqual(jane)
    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it('retries after a failed request', async () => {
    const fetch = vi
      .fn()
      .mockResolvedValueOnce(new Response(null, { status: 500 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(jane), { status: 200 }))
    vi.stubGlobal('fetch', fetch)
    const { loadUser } = await freshSession()

    await expect(loadUser()).rejects.toThrow()
    await expect(loadUser()).resolves.toEqual(jane)
  })

  it('setUser(null) signs out without another request', async () => {
    const fetch = vi.fn()
    vi.stubGlobal('fetch', fetch)
    const { loadUser, setUser, user } = await freshSession()

    setUser(null)

    await expect(loadUser()).resolves.toBeNull()
    expect(user.value).toBeNull()
    expect(fetch).not.toHaveBeenCalled()
  })
})
