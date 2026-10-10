import { afterEach, describe, expect, it, vi } from 'vitest'
import { createProject, deleteProject, listProjects, login } from './api'

function respond(status: number, body?: unknown) {
  const fetch = vi.fn().mockResolvedValue(
    new Response(body === undefined ? null : JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json' },
    }),
  )
  vi.stubGlobal('fetch', fetch)
  return fetch
}

afterEach(() => vi.unstubAllGlobals())

describe('api', () => {
  it('sends JSON to the same-origin API', async () => {
    const fetch = respond(201, { id: '1', name: 'Watchdog', createdAt: '2026-10-10T12:00:00+00:00' })

    await expect(createProject('Watchdog')).resolves.toMatchObject({ name: 'Watchdog' })
    expect(fetch).toHaveBeenCalledWith(
      '/api/projects',
      expect.objectContaining({ method: 'POST', body: '{"name":"Watchdog"}' }),
    )
  })

  it('translates domain errors by messageKey', async () => {
    respond(422, {
      detail: 'Project name must be 1-100 characters.',
      messageKey: 'project.name.invalid',
      messageParameters: { '%max%': 100 },
    })

    await expect(createProject(' ')).rejects.toThrow('Nazwa projektu musi mieć od 1 do 100 znaków.')
  })

  it('falls back to a generic message for 403 and to detail otherwise', async () => {
    respond(403, { detail: 'Access Denied.' })
    await expect(listProjects()).rejects.toThrow('Brak uprawnień.')

    respond(500, { detail: 'Something broke.' })
    await expect(listProjects()).rejects.toThrow('Something broke.')
  })

  it('treats 204 as a successful delete and maps 404', async () => {
    respond(204)
    await expect(deleteProject('1')).resolves.toBeUndefined()

    respond(404, { messageKey: 'project.not_found', messageParameters: {} })
    await expect(deleteProject('1')).rejects.toThrow('Projekt nie istnieje.')
  })

  it('translates invalid credentials', async () => {
    respond(401, { error: 'Invalid credentials.' })

    await expect(login('a@example.com', 'x')).rejects.toThrow('Nieprawidłowy e-mail lub hasło.')
  })
})
