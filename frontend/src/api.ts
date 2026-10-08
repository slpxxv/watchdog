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
