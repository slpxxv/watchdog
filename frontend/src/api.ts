// Same-origin calls: fetch sends the session cookie by default.

export interface Me {
  id: string
  email: string
  roles: string[] // role codes, e.g. "super_admin"
  permissions: string[] // effective, e.g. "role.manage"
  createdAt: string // ISO 8601
}

const errors: Record<string, string> = {
  'Invalid credentials.': 'Nieprawidłowy e-mail lub hasło.',
}

function api(path: string, init: RequestInit = {}): Promise<Response> {
  return fetch(`/api${path}`, {
    ...init,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...init.headers },
  })
}

export async function login(email: string, password: string): Promise<Me> {
  const res = await api('/login', { method: 'POST', body: JSON.stringify({ email, password }) })
  if (!res.ok) {
    const { error } = await res.json().catch(() => ({ error: '' }))
    // Throttling and other messages come from Symfony as-is (English).
    throw new Error(errors[error] ?? (error || `Błąd logowania (HTTP ${res.status}).`))
  }
  return res.json()
}

export async function me(): Promise<Me | null> {
  const res = await api('/me')
  if (res.status === 401) return null
  if (!res.ok) throw new Error(`GET /api/me: HTTP ${res.status}`)
  return res.json()
}

export async function logout(): Promise<void> {
  await api('/logout', { method: 'POST' })
}

export interface Project {
  id: string
  name: string
  createdAt: string // ISO 8601
}

// Domain errors arrive as problem+json with a stable messageKey; anything else falls back to `detail`.
const problemMessages: Record<string, (params: Record<string, string | number>) => string> = {
  'project.name.invalid': (p) => `Nazwa projektu musi mieć od 1 do ${p['%max%']} znaków.`,
  'project.not_found': () => 'Projekt nie istnieje.',
  'source.name.invalid': (p) => `Nazwa źródła musi mieć od 1 do ${p['%max%']} znaków.`,
  'source.not_found': () => 'Źródło nie istnieje.',
  'source.revoked': () => 'Źródło jest odwołane. Utwórz nowe, żeby dostać token.',
}

async function body<T>(res: Response): Promise<T> {
  if (res.ok) return res.json()
  const problem = await res.json().catch(() => ({}))
  const message = problemMessages[problem.messageKey]?.(problem.messageParameters ?? {})
  if (message) throw new Error(message)
  if (res.status === 403) throw new Error('Brak uprawnień.')
  throw new Error(problem.detail || `Błąd serwera (HTTP ${res.status}).`)
}

export async function listProjects(): Promise<Project[]> {
  return body(await api('/projects'))
}

export async function createProject(name: string): Promise<Project> {
  return body(await api('/projects', { method: 'POST', body: JSON.stringify({ name }) }))
}

export async function renameProject(id: string, name: string): Promise<Project> {
  return body(await api(`/projects/${id}`, { method: 'PATCH', body: JSON.stringify({ name }) }))
}

async function noContent(res: Response): Promise<void> {
  if (!res.ok) await body(res) // throws the mapped error
}

export async function getProject(id: string): Promise<Project> {
  return body(await api(`/projects/${id}`))
}

export async function deleteProject(id: string): Promise<void> {
  return noContent(await api(`/projects/${id}`, { method: 'DELETE' }))
}

export interface Source {
  id: string
  projectId: string
  name: string
  tokenPrefix: string // first characters of the token, to recognise it
  createdAt: string // ISO 8601
  revokedAt: string | null
}

// Returned only when a token is created or rotated; the server never shows it again.
export interface NewSource {
  source: Source
  token: string
}

export async function listSources(projectId: string): Promise<Source[]> {
  return body(await api(`/projects/${projectId}/sources`))
}

export async function createSource(projectId: string, name: string): Promise<NewSource> {
  return body(await api(`/projects/${projectId}/sources`, { method: 'POST', body: JSON.stringify({ name }) }))
}

export async function rotateSourceToken(id: string): Promise<NewSource> {
  return body(await api(`/sources/${id}/rotate-token`, { method: 'POST' }))
}

export async function revokeSource(id: string): Promise<void> {
  return noContent(await api(`/sources/${id}/revoke`, { method: 'POST' }))
}
