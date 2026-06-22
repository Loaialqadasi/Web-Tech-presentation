<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { fetchForumPosts, createForumPost, deleteForumPost, updateForumPost, fetchEvents, errMsg } from '../service/api.js'
import { authState } from '../service/auth.js'
import { formatDate } from '../utils/format.js'
import { validateSchema, isValid, forumPostSchema } from '../utils/validators.js'
import LoadingSpinner from '../components/shared/LoadingSpinner.vue'
import EmptyState from '../components/shared/EmptyState.vue'

const router = useRouter()
const loading = ref(true)
const posts = ref([])
const events = ref([])
const error = ref('')
const submitError = ref('')
const submitSuccess = ref('')
const actionLoading = ref(false)

// Filter & Search
const searchQuery = ref('')
const filterEventId = ref('')

const filteredPosts = computed(() => {
  return posts.value.filter(p => {
    const matchesSearch = p.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          p.content.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          p.author.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesEvent = !filterEventId.value || p.eventId === Number(filterEventId.value)
    return matchesSearch && matchesEvent
  })
})

// Edit Modal State
const isEditModalOpen = ref(false)
const editingPostId = ref(null)
const editTitle = ref('')
const editContent = ref('')
const editEventId = ref('')
const editError = ref('')

function openEditModal(postItem) {
  editingPostId.value = postItem.postId
  editTitle.value = postItem.title
  editContent.value = postItem.content
  editEventId.value = postItem.eventId || ''
  editError.value = ''
  isEditModalOpen.value = true
}

async function handleEditSubmit() {
  editError.value = ''
  const editErrors = validateSchema({
    title: editTitle.value,
    content: editContent.value,
  }, forumPostSchema)
  if (!isValid(editErrors)) {
    editError.value = Object.values(editErrors)[0]
    return
  }

  actionLoading.value = true
  try {
    const payload = {
      title: editTitle.value.trim(),
      content: editContent.value.trim(),
      eventId: editEventId.value ? Number(editEventId.value) : null
    }

    await updateForumPost(editingPostId.value, payload)
    isEditModalOpen.value = false
    
    // Reload
    const pts = await fetchForumPosts()
    posts.value = pts
  } catch (e) {
    editError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

// Form Fields
const newTitle = ref('')
const newContent = ref('')
const selectedEventId = ref('')

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [pts, evs] = await Promise.all([
      fetchForumPosts(),
      fetchEvents(),
    ])
    posts.value = pts
    events.value = evs.filter(e => e.status !== 'cancelled')
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

async function handleSubmit() {
  submitError.value = ''
  submitSuccess.value = ''

  const errors = validateSchema({
    title: newTitle.value,
    content: newContent.value,
  }, forumPostSchema)
  if (!isValid(errors)) {
    submitError.value = Object.values(errors)[0]
    return
  }

  actionLoading.value = true
  try {
    const payload = {
      title: newTitle.value.trim(),
      content: newContent.value.trim(),
    }
    if (selectedEventId.value) {
      payload.eventId = Number(selectedEventId.value)
    }

    await createForumPost(payload)
    submitSuccess.value = 'Discussion thread posted successfully!'
    newTitle.value = ''
    newContent.value = ''
    selectedEventId.value = ''

    // Reload posts list
    const pts = await fetchForumPosts()
    posts.value = pts
  } catch (e) {
    submitError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

async function handleDelete(postId) {
  if (!window.confirm('Delete this forum thread? All comments will also be permanently deleted.')) return

  actionLoading.value = true
  try {
    await deleteForumPost(postId)
    posts.value = posts.value.filter(p => p.postId !== postId)
  } catch (e) {
    alert(errMsg(e))
  } finally {
    actionLoading.value = false
  }
}

function getEventTitle(eventId) {
  if (!eventId) return ''
  const e = events.value.find(item => item.id === eventId)
  return e ? e.title : `Event #${eventId}`
}

function viewPost(postId) {
  router.push({ name: 'forum-detail', params: { id: postId } })
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-8 space-y-8">
    <header>
      <h1 class="text-2xl font-bold text-slate-800">Forum Discussions</h1>
      <p class="text-slate-500 text-sm">Ask questions, share experiences, and coordinate with other attendees.</p>
    </header>

    <div v-if="loading" class="py-12">
      <LoadingSpinner label="Loading discussion threads..." />
    </div>

    <div v-else-if="error" class="bg-white rounded-xl shadow-sm border border-slate-100 p-8">
      <EmptyState icon="⚠️" title="Error Loading Forum" :description="error">
        <template #action>
          <button class="px-6 py-2 bg-indigo-600 text-white font-semibold rounded-lg" @click="loadData">
            Try Again
          </button>
        </template>
      </EmptyState>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left Column: Start Discussion Form -->
      <section class="lg:col-span-1 space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-4">
          <h2 class="text-base font-bold text-slate-800 pb-2 border-b border-slate-100">Start a Thread</h2>
          
          <div v-if="!authState.user" class="text-xs text-slate-500 leading-relaxed py-2">
            🔑 Please <router-link :to="{ name: 'login' }" class="text-indigo-600 font-bold hover:underline">sign in</router-link> to start a new discussion thread.
          </div>
          
          <form v-else @submit.prevent="handleSubmit" class="space-y-4">
            <!-- Topic Title -->
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-700" for="newTitle">Discussion Title</label>
              <input 
                id="newTitle"
                v-model="newTitle"
                type="text"
                placeholder="Brief summary of topic..."
                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"
              />
            </div>

            <!-- Tag Event (optional) -->
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-700" for="eventTag">Tag an Event (Optional)</label>
              <select 
                id="eventTag"
                v-model="selectedEventId"
                class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:border-indigo-500"
              >
                <option value="">No tag</option>
                <option v-for="e in events" :key="e.id" :value="e.id">
                  {{ e.title }}
                </option>
              </select>
            </div>

            <!-- Post Content -->
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-700" for="newContent">Post Body</label>
              <textarea 
                id="newContent"
                v-model="newContent"
                rows="6"
                placeholder="Detail your question or idea (minimum 20 characters)..."
                class="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"
              ></textarea>
            </div>

            <p v-if="submitError" class="text-xs text-red-600 bg-red-50 p-2.5 rounded border border-red-100">{{ submitError }}</p>
            <p v-if="submitSuccess" class="text-xs text-emerald-600 bg-emerald-50 p-2.5 rounded border border-emerald-100">{{ submitSuccess }}</p>

            <button 
              type="submit" 
              class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-lg transition disabled:opacity-50"
              :disabled="actionLoading"
            >
              Post Thread
            </button>
          </form>
        </div>
      </section>

      <!-- Right Column: Threads List -->
      <section class="lg:col-span-2 space-y-4">
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 pb-2 border-b border-slate-50">
          <h2 class="text-lg font-bold text-slate-800">Discussion List</h2>
          <!-- Search & Filter Controls -->
          <div class="flex flex-wrap gap-2">
            <input 
              v-model="searchQuery"
              type="text"
              placeholder="Search discussions..."
              class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-indigo-500 w-full sm:w-44 lg:w-56"
            />
            <select 
              v-model="filterEventId"
              class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-indigo-500 w-full sm:w-auto max-w-full"
            >
              <option value="">All Events</option>
              <option v-for="e in events" :key="e.id" :value="e.id">
                {{ e.title }}
              </option>
            </select>
          </div>
        </div>

        <div v-if="filteredPosts.length === 0" class="bg-white rounded-xl shadow-sm border border-slate-100 p-8 text-center text-slate-400">
          No discussion threads found. Start a new topic or clear filters!
        </div>

        <div v-else class="space-y-4">
          <div 
            v-for="p in filteredPosts" 
            :key="p.postId" 
            class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 space-y-4 hover:shadow-md transition duration-150"
          >
            <div class="space-y-1">
              <div class="flex flex-wrap items-center gap-2">
                <span v-if="p.eventId" class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                  🏷️ {{ getEventTitle(p.eventId) }}
                </span>
                <span class="text-xs text-slate-400">
                  Posted by <strong class="text-slate-600">{{ p.author }}</strong> • {{ formatDate(p.createdAt) }}
                </span>
              </div>
              <h3 
                class="font-black text-slate-800 text-lg hover:text-indigo-600 cursor-pointer"
                @click="viewPost(p.postId)"
              >
                {{ p.title }}
              </h3>
            </div>

            <!-- Body Snippet -->
            <p class="text-slate-600 text-sm leading-relaxed line-clamp-3">
              {{ p.content }}
            </p>

            <!-- Card Actions -->
            <div class="flex justify-between items-center pt-2 border-t border-slate-50 text-xs">
              <span class="text-slate-500 font-semibold flex items-center gap-1.5">
                💬 {{ p.commentCount }} reply(ies)
              </span>

              <div class="flex gap-3">
                <button 
                  v-if="authState.user && (p.userId === authState.user.id || authState.user.role === 'admin')"
                  class="text-red-500 hover:text-red-700 font-bold transition"
                  @click="handleDelete(p.postId)"
                  :disabled="actionLoading"
                >
                  Delete
                </button>
                
                <button 
                  v-if="authState.user && (p.userId === authState.user.id || authState.user.role === 'admin')"
                  class="text-indigo-600 hover:text-indigo-800 font-bold transition"
                  @click="openEditModal(p)"
                  :disabled="actionLoading"
                >
                  Edit
                </button>
                
                <button 
                  class="text-indigo-600 hover:text-indigo-800 font-bold transition"
                  @click="viewPost(p.postId)"
                >
                  Read & Reply →
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Edit Thread Modal -->
    <div v-if="isEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl border border-slate-100 max-w-lg w-full p-6 space-y-4">
        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
          <h3 class="text-base font-bold text-slate-800">Edit Discussion Thread</h3>
          <button @click="isEditModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>
        
        <form @submit.prevent="handleEditSubmit" class="space-y-4">
          <!-- Edit Title -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700" for="editTitle">Discussion Title</label>
            <input 
              id="editTitle"
              v-model="editTitle"
              type="text"
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"
            />
          </div>

          <!-- Edit Event Tag -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700" for="editEventTag">Tag an Event (Optional)</label>
            <select 
              id="editEventTag"
              v-model="editEventId"
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:border-indigo-500"
            >
              <option value="">No tag</option>
              <option v-for="e in events" :key="e.id" :value="e.id">
                {{ e.title }}
              </option>
            </select>
          </div>

          <!-- Edit Content -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700" for="editContent">Post Body</label>
            <textarea 
              id="editContent"
              v-model="editContent"
              rows="6"
              class="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500"
            ></textarea>
          </div>

          <p v-if="editError" class="text-xs text-red-600 bg-red-50 p-2.5 rounded border border-red-100">{{ editError }}</p>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button 
              type="button"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg transition"
              @click="isEditModalOpen = false"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg transition disabled:opacity-50"
              :disabled="actionLoading"
            >
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
