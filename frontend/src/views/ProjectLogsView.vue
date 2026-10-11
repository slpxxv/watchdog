<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import { listSources, searchLogs, tailLogs, type LogFilters, type LogLevel, type LogLine, type Source } from '../api'
import { plainText, TailBuffer } from '../logTail'
import { loadUser } from '../session'

const POLL_MS = 2000
const LEVELS: LogLevel[] = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']
const RANGES = [
  { value: '15m', label: 'Ostatnie 15 min', minutes: 15 },
  { value: '1h', label: 'Ostatnia godzina', minutes: 60 },
  { value: '24h', label: 'Ostatnie 24 h', minutes: 24 * 60 },
  { value: '7d', label: 'Ostatnie 7 dni', minutes: 7 * 24 * 60 },
] as const

const props = defineProps<{ id: string }>()
const route = useRoute()
const router = useRouter()
const scroller = useTemplateRef<HTMLElement>('scroller')

const canView = ref(false)
const error = ref('')
const loading = ref(false)
const sources = ref<Source[]>([])
const lines = ref<LogLine[]>([]) // search: newest first
const nextCursor = ref<string | null>(null)
const searchFrom = ref('')
const retentionDays = ref<number | null>(null)
const tailLines = ref<LogLine[]>([]) // live: arrival order, newest last
const newCount = ref(0)
const expanded = ref(new Set<number>())
const qInput = ref('')

const buffer = new TailBuffer()
let lastId = 0
let timer: ReturnType<typeof setInterval> | undefined
let atBottom = true

// The URL holds the filters, so a view can be shared or bookmarked.
const param = (key: string) => (typeof route.query[key] === 'string' ? (route.query[key] as string) : '')
const level = computed(() => param('level'))
const source = computed(() => param('source'))
const range = computed(() => RANGES.find((r) => r.value === param('range')) ?? RANGES[2])
const q = computed(() => param('q'))
const live = computed(() => param('live') === '1')
const filters = computed<LogFilters>(() => ({
  level: level.value || undefined,
  sources: source.value ? [source.value] : undefined,
  q: q.value || undefined,
}))
const shown = computed(() => (live.value ? tailLines.value : lines.value))

const plural = new Intl.PluralRules('pl')
const retentionLabel = computed(() => {
  const days = retentionDays.value
  if (days === null) return ''
  return `Logi są przechowywane ${days} ${plural.select(days) === 'one' ? 'dzień' : 'dni'}, starsze są usuwane automatycznie.`
})
const newLinesLabel = computed(() => {
  const forms: Partial<Record<Intl.LDMLPluralRule, string>> = { one: 'nowa linia', few: 'nowe linie' }
  return `${newCount.value} ${forms[plural.select(newCount.value)] ?? 'nowych linii'}`
})

const timeFormat = new Intl.DateTimeFormat('pl-PL', {
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  fractionalSecondDigits: 3,
  hour12: false,
})
const dayFormat = new Intl.DateTimeFormat('pl-PL', { day: '2-digit', month: '2-digit' })
const fullFormat = new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium', timeStyle: 'medium' })

function clock(iso: string): string {
  const date = new Date(iso)
  const time = timeFormat.format(date)
  return date.toDateString() === new Date().toDateString() ? time : `${dayFormat.format(date)} ${time}`
}

function hasContext(line: LogLine): boolean {
  return Object.keys(line.context).length > 0
}

function updateQuery(patch: Record<string, string | undefined>) {
  const next: LocationQueryRaw = { ...route.query }
  for (const [key, value] of Object.entries(patch)) {
    if (value) next[key] = value
    else delete next[key]
  }
  router.replace({ query: next })
}

async function guarded(action: () => Promise<void>) {
  error.value = ''
  loading.value = true
  try {
    await action()
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  } finally {
    loading.value = false
  }
}

function search() {
  return guarded(async () => {
    const from = new Date(Date.now() - range.value.minutes * 60_000).toISOString()
    const page = await searchLogs(props.id, { ...filters.value, from })
    lines.value = page.items
    nextCursor.value = page.nextCursor
    searchFrom.value = page.from
    retentionDays.value = page.retentionDays
  })
}

function loadOlder() {
  if (!nextCursor.value) return
  return guarded(async () => {
    const page = await searchLogs(props.id, { ...filters.value, from: searchFrom.value, cursor: nextCursor.value! })
    lines.value = [...lines.value, ...page.items]
    nextCursor.value = page.nextCursor
  })
}

async function poll() {
  if (document.hidden) return // resumes from lastId when the tab is visible again
  try {
    const tail = await tailLogs(props.id, lastId, filters.value)
    lastId = tail.lastId
    const added = buffer.add(tail.items)
    if (added === 0) return
    tailLines.value = buffer.lines()
    if (atBottom) {
      await nextTick()
      scrollToBottom()
    } else {
      newCount.value += added
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  }
}

function startLive() {
  buffer.clear()
  tailLines.value = []
  lastId = 0
  newCount.value = 0
  atBottom = true
  void poll()
  // ponytail: polling, since every open SSE stream would hold a PHP process under symfony serve;
  // move to Mercure once many people watch at the same time.
  timer = setInterval(poll, POLL_MS)
}

function stopLive() {
  clearInterval(timer)
  timer = undefined
}

function refresh() {
  stopLive()
  expanded.value = new Set()
  if (live.value) startLive()
  else void search()
}

function scrollToBottom() {
  const el = scroller.value
  if (el) el.scrollTop = el.scrollHeight
  newCount.value = 0
  atBottom = true
}

function onScroll() {
  const el = scroller.value
  if (!el) return
  atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 24
  if (atBottom) newCount.value = 0
}

function toggle(id: number) {
  const next = new Set(expanded.value)
  if (!next.delete(id)) next.add(id)
  expanded.value = next
}

async function copy(line: LogLine) {
  await navigator.clipboard.writeText(JSON.stringify(line, null, 2))
}

function onVisibility() {
  if (!document.hidden && timer) void poll()
}

onMounted(async () => {
  qInput.value = q.value
  document.addEventListener('visibilitychange', onVisibility)
  const user = await loadUser().catch(() => null)
  if (!user?.permissions.includes('log.view')) {
    error.value = 'Brak uprawnień do przeglądania logów.'
    return
  }
  canView.value = true
  listSources(props.id)
    .then((list) => (sources.value = list))
    .catch(() => {}) // the filter is optional; the logs still load
  refresh()
})

onBeforeUnmount(() => {
  stopLive()
  document.removeEventListener('visibilitychange', onVisibility)
})

watch(
  () => [level.value, source.value, range.value, q.value, live.value],
  () => {
    qInput.value = q.value
    if (canView.value) refresh()
  },
)
</script>

<template>
  <p v-if="error" class="alert" role="alert">{{ error }}</p>

  <template v-if="canView">
    <form class="log-filters" @submit.prevent="updateQuery({ q: qInput.trim() })">
      <label class="visually-hidden" for="log-level">Poziom</label>
      <select
        id="log-level"
        :value="level"
        @change="updateQuery({ level: ($event.target as HTMLSelectElement).value })"
      >
        <option value="">Wszystkie poziomy</option>
        <option v-for="l in LEVELS" :key="l" :value="l">od {{ l }}</option>
      </select>

      <label class="visually-hidden" for="log-source">Źródło</label>
      <select
        id="log-source"
        :value="source"
        @change="updateQuery({ source: ($event.target as HTMLSelectElement).value })"
      >
        <option value="">Wszystkie źródła</option>
        <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>

      <label class="visually-hidden" for="log-range">Zakres czasu</label>
      <select
        id="log-range"
        :value="range.value"
        :disabled="live"
        :title="live ? 'Na żywo pokazuje bieżące linie' : undefined"
        @change="updateQuery({ range: ($event.target as HTMLSelectElement).value })"
      >
        <option v-for="r in RANGES" :key="r.value" :value="r.value">{{ r.label }}</option>
      </select>

      <label class="visually-hidden" for="log-q">Szukaj w treści</label>
      <input id="log-q" v-model="qInput" type="search" placeholder="Szukaj w treści i naciśnij Enter" />

      <label class="live-toggle">
        <input type="checkbox" :checked="live" @change="updateQuery({ live: live ? undefined : '1' })" />
        <span class="live-dot" :class="{ on: live }" aria-hidden="true" />Na żywo
      </label>
    </form>

    <section ref="scroller" class="terminal" aria-label="Logi" :aria-busy="loading" @scroll="onScroll">
      <p v-if="!shown.length" class="terminal-empty">
        {{ loading ? 'Ładowanie…' : live ? 'Czekam na nowe linie…' : 'Brak logów w tym zakresie.' }}
      </p>

      <ol v-else class="log-lines">
        <li v-for="line in shown" :key="line.id" class="log-line" :class="`lvl-${line.level}`">
          <button type="button" class="log-row" :aria-expanded="expanded.has(line.id)" @click="toggle(line.id)">
            <time :datetime="line.timestamp">{{ clock(line.timestamp) }}</time>
            <span class="log-level">{{ line.level }}</span>
            <span class="log-source">{{ line.sourceName ?? '—' }}</span>
            <span class="log-message">{{ plainText(line.message) }}</span>
          </button>
          <div v-if="expanded.has(line.id)" class="log-detail">
            <p class="log-message-full">{{ plainText(line.message) }}</p>
            <dl>
              <dt>Czas</dt>
              <dd>{{ fullFormat.format(new Date(line.timestamp)) }}</dd>
              <dt>Przyjęto</dt>
              <dd>{{ fullFormat.format(new Date(line.receivedAt)) }}</dd>
              <dt>Źródło</dt>
              <dd>{{ line.sourceName ?? line.sourceId }}</dd>
            </dl>
            <pre v-if="hasContext(line)">{{ JSON.stringify(line.context, null, 2) }}</pre>
            <button type="button" class="ghost" @click="copy(line)">Kopiuj jako JSON</button>
          </div>
        </li>
      </ol>

      <div v-if="!live && nextCursor" class="terminal-more">
        <button type="button" class="ghost" :disabled="loading" @click="loadOlder">Załaduj starsze</button>
      </div>
    </section>

    <button v-if="live && newCount" type="button" class="new-lines" @click="scrollToBottom">
      {{ newLinesLabel }}
    </button>
    <p v-if="retentionLabel" class="retention-note muted">{{ retentionLabel }}</p>
  </template>
</template>
