<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef } from 'vue'
import { createSource, listSources, revokeSource, rotateSourceToken, type NewSource, type Source } from '../api'
import { loadUser } from '../session'

const MAX_NAME_LENGTH = 100 // mirrors Source::MAX_NAME_LENGTH

const props = defineProps<{ id: string }>()
const sources = ref<Source[] | null>(null)
const error = ref('')
const pending = ref(false)
const newName = ref('')
const revealed = ref<NewSource | null>(null)
const copied = ref(false)
const pendingAction = ref<{ kind: 'rotate' | 'revoke'; source: Source } | null>(null)
const tokenDialog = useTemplateRef<HTMLDialogElement>('token-dialog')
const confirmDialog = useTemplateRef<HTMLDialogElement>('confirm-dialog')

const dateFormat = new Intl.DateTimeFormat('pl-PL', { day: 'numeric', month: 'short', year: 'numeric' })
const curlExample = computed(() =>
  revealed.value
    ? [
        `curl -X POST ${window.location.origin}/api/v1/ingest/logs \\`,
        `  -H 'Authorization: Bearer ${revealed.value.token}' \\`,
        `  -H 'Content-Type: application/json' \\`,
        `  -d '[{"level": "info", "message": "Pierwszy log z Watchdoga"}]'`,
      ].join('\n')
    : '',
)

async function run(action: () => Promise<void>) {
  error.value = ''
  pending.value = true
  try {
    await action()
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  } finally {
    pending.value = false
  }
}

onMounted(() =>
  run(async () => {
    const user = await loadUser()
    if (!user?.permissions.includes('source.manage')) throw new Error('Brak uprawnień do zarządzania źródłami.')
    sources.value = await listSources(props.id)
  }),
)

function reveal(created: NewSource) {
  revealed.value = created
  copied.value = false
  tokenDialog.value?.showModal()
}

function forgetToken() {
  revealed.value = null // the token leaves memory with the dialog
}

async function copyToken() {
  if (!revealed.value) return
  await navigator.clipboard.writeText(revealed.value.token)
  copied.value = true
}

function create() {
  return run(async () => {
    const created = await createSource(props.id, newName.value.trim())
    sources.value?.push(created.source)
    newName.value = ''
    reveal(created)
  })
}

function replace(updated: Source) {
  sources.value = sources.value?.map((s) => (s.id === updated.id ? updated : s)) ?? null
}

function ask(kind: 'rotate' | 'revoke', source: Source) {
  pendingAction.value = { kind, source }
  if (!confirmDialog.value) return
  confirmDialog.value.returnValue = '' // Esc keeps the previous value; never let it count as a confirm
  confirmDialog.value.showModal()
}

function onConfirmClose() {
  const action = pendingAction.value
  const confirmed = confirmDialog.value?.returnValue === 'confirm'
  pendingAction.value = null
  if (!action || !confirmed) return
  return run(async () => {
    if (action.kind === 'rotate') {
      const rotated = await rotateSourceToken(action.source.id)
      replace(rotated.source)
      reveal(rotated)
    } else {
      await revokeSource(action.source.id)
      replace({ ...action.source, revokedAt: new Date().toISOString() })
    }
  })
}
</script>

<template>
  <p v-if="error" class="alert" role="alert">{{ error }}</p>

  <section v-if="sources" class="panel" aria-label="Źródła logów">
    <form class="panel-head inline" @submit.prevent="create">
      <label for="new-source" class="visually-hidden">Nazwa nowego źródła</label>
      <input
        id="new-source"
        v-model="newName"
        placeholder="Nazwa źródła, np. API produkcyjne"
        required
        pattern=".*\S.*"
        :maxlength="MAX_NAME_LENGTH"
      />
      <button type="submit" :disabled="pending">Dodaj źródło</button>
    </form>

    <p v-if="!sources.length" class="empty">
      Źródło to aplikacja albo serwer, który wysyła logi. Dodaj pierwsze, żeby dostać token.
    </p>

    <ul v-else class="rows">
      <li v-for="source in sources" :key="source.id" class="row" :class="{ 'row-muted': source.revokedAt }">
        <span class="row-name">{{ source.name }}</span>
        <code class="token-prefix" :title="`Token zaczyna się od ${source.tokenPrefix}`"
          >{{ source.tokenPrefix }}…</code
        >
        <span class="chip" :class="source.revokedAt ? 'chip-off' : 'chip-on'">{{
          source.revokedAt ? 'Odwołane' : 'Aktywne'
        }}</span>
        <time class="row-meta" :datetime="source.createdAt">{{ dateFormat.format(new Date(source.createdAt)) }}</time>
        <span v-if="!source.revokedAt" class="row-actions">
          <button type="button" class="ghost" :disabled="pending" @click="ask('rotate', source)">Nowy token</button>
          <button type="button" class="ghost danger" :disabled="pending" @click="ask('revoke', source)">Odwołaj</button>
        </span>
      </li>
    </ul>
  </section>
  <p v-else-if="!error" class="muted">Ładowanie…</p>

  <dialog ref="token-dialog" class="token-dialog" aria-labelledby="token-title" @close="forgetToken">
    <form v-if="revealed" method="dialog">
      <h2 id="token-title">Token źródła „{{ revealed.source.name }}”</h2>
      <p class="muted">Skopiuj go teraz. Po zamknięciu tego okna nie da się go już wyświetlić.</p>
      <div class="token-box">
        <code>{{ revealed.token }}</code>
        <button type="button" class="ghost" @click="copyToken">{{ copied ? 'Skopiowano' : 'Kopiuj' }}</button>
      </div>
      <p class="muted">Przykład wysłania logu:</p>
      <pre>{{ curlExample }}</pre>
      <div class="dialog-actions">
        <button value="close" autofocus>Gotowe</button>
      </div>
    </form>
  </dialog>

  <dialog ref="confirm-dialog" aria-labelledby="confirm-title" @close="onConfirmClose">
    <form v-if="pendingAction" method="dialog">
      <template v-if="pendingAction.kind === 'rotate'">
        <h2 id="confirm-title">Wygenerować nowy token?</h2>
        <p class="muted">
          Obecny token źródła „{{ pendingAction.source.name }}” przestanie działać od razu. Zaktualizuj go w aplikacji,
          która wysyła logi.
        </p>
      </template>
      <template v-else>
        <h2 id="confirm-title">Odwołać źródło?</h2>
        <p class="muted">
          Źródło „{{ pendingAction.source.name }}” przestanie przyjmować logi na stałe. Zebrane logi zostaną.
        </p>
      </template>
      <div class="dialog-actions">
        <button value="cancel" class="ghost" autofocus>Anuluj</button>
        <button value="confirm" :class="{ destructive: pendingAction.kind === 'revoke' }">
          {{ pendingAction.kind === 'rotate' ? 'Wygeneruj token' : 'Odwołaj źródło' }}
        </button>
      </div>
    </form>
  </dialog>
</template>
