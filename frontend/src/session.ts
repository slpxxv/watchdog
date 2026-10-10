import { ref } from 'vue'
import { me, type Me } from './api'

// The signed-in user, shared by the app shell and the views: one /api/me request per session.
export const user = ref<Me | null>(null)
let request: Promise<Me | null> | null = null

export function loadUser(): Promise<Me | null> {
  request ??= me().then(
    (u) => (user.value = u),
    (e) => {
      request = null
      throw e
    },
  )
  return request
}

export function setUser(u: Me | null): void {
  user.value = u
  request = Promise.resolve(u)
}
