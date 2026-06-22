<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fetchForumPost, fetchComments, createComment, deleteComment, fetchEvents, errMsg } from '../service/api.js'
import { authState } from '../service/auth.js'
import { formatDate } from '../utils/format.js'
import { minLength, required } from '../utils/validators.js'
import LoadingSpinner from '../components/shared/LoadingSpinner.vue'
import EmptyState from '../components/shared/EmptyState.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const post = ref(null)
const comments = ref([])
const events = ref([])
const error = ref('')

const replyContent = ref('')
const submitError = ref('')
const actionLoading = ref(false)

const postId = Number(route.params.id)

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [p, c, evs] = await Promise.all([
      fetchForumPost(postId),
      fetchComments(postId),
      fetchEvents().catch(() => []),
    ])
    post.value = p
    comments.value = c
    events.value = evs
  } catch (e) {
    error.value = errMsg(e)
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

async function handleSubmitReply() {
  submitError.value = ''
  const replyErr = required(replyContent.value.trim(), 'Reply') || minLength(replyContent.value.trim(), 2, 'Reply')
  if (replyErr) {
    submitError.value = replyErr
    return
  }

  actionLoading.value = true
  try {
    await createComment({
      postId,
      content: replyContent.value.trim(),
    })
    replyContent.value = ''
    // Reload comments
    comments.value = await fetchComments(postId)
  } catch (e) {
    submitError.value = errMsg(e)
  } finally {
    actionLoading.value = false
  }
}

async function handleDeleteComment(commentId) {
  if (!window.confirm('Delete this comment?')) return

  actionLoading.value = true
  try {
    await deleteComment(commentId)
    comments.value = comments.value.filter(c => c.commentId !== commentId)
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

function goBack() {
  router.push({ name: 'forum' })
}
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
    <div>
      <button class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition" @click="goBack">
        ← Back to Forum
      </button>
    </div>

    <div v-if="loading" class="py-12">
      <LoadingSpinner label="Loading thread details..." />
    </div>

    <div v-else-if="error || !post" class="bg-white rounded-xl shadow-sm border border-slate-100 p-8">
      <EmptyState icon="⚠️" title="Error Loading Discussion" :description="error || 'Thread not found.'">
        <template #action>
          <button class="px-6 py-2 bg-indigo-600 text-white font-semibold rounded-lg" @click="loadData">
            Try Again
          </button>
        </template>
      </EmptyState>
    </div>

    <div v-else class="space-y-6">
      <!-- Main Thread Post -->
      <article class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-4">
        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <span v-if="post.eventId" class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
              🏷️ {{ getEventTitle(post.eventId) }}
            </span>
            <span class="text-xs text-slate-400">
              Started by <strong class="text-slate-600">{{ post.author }}</strong> • {{ formatDate(post.createdAt) }}
            </span>
          </div>
          <h1 class="text-2xl font-black text-slate-800">{{ post.title }}</h1>
        </div>

        <p class="text-slate-700 text-sm leading-relaxed whitespace-pre-wrap">
          {{ post.content }}
        </p>
      </article>

      <!-- Comments/Replies Section -->
      <section class="space-y-4">
        <h2 class="text-lg font-bold text-slate-800">Replies ({{ comments.length }})</h2>

        <!-- Comments List -->
        <div v-if="comments.length === 0" class="bg-white rounded-xl shadow-sm border border-slate-100 p-8 text-center text-slate-400 text-xs">
          No replies yet. Start the conversation!
        </div>

        <div v-else class="space-y-3">
          <div 
            v-for="c in comments" 
            :key="c.commentId" 
            class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 space-y-2"
          >
            <div class="flex justify-between items-center text-xs">
              <span class="font-bold text-slate-700">{{ c.author }}</span>
              <span class="text-slate-400">{{ formatDate(c.createdAt) }}</span>
            </div>
            
            <p class="text-slate-600 text-xs leading-relaxed whitespace-pre-wrap">
              {{ c.content }}
            </p>

            <!-- Delete comment option -->
            <div v-if="authState.user && (c.userId === authState.user.id || authState.user.role === 'admin')" class="flex justify-end">
              <button 
                class="text-red-500 hover:text-red-700 text-[10px] font-bold transition"
                @click="handleDeleteComment(c.commentId)"
                :disabled="actionLoading"
              >
                Delete Reply
              </button>
            </div>
          </div>
        </div>

        <!-- Add Reply Box -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 space-y-4">
          <h3 class="text-sm font-bold text-slate-800">Add a Reply</h3>
          
          <div v-if="!authState.user" class="text-xs text-slate-500 leading-relaxed py-2">
            🔑 Please <router-link :to="{ name: 'login' }" class="text-indigo-600 font-bold hover:underline">sign in</router-link> to submit a reply.
          </div>
          
          <form v-else @submit.prevent="handleSubmitReply" class="space-y-3">
            <textarea 
              v-model="replyContent"
              rows="4"
              placeholder="Type your reply here..."
              class="w-full px-3.5 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-indigo-500"
            ></textarea>
            
            <p v-if="submitError" class="text-xs text-red-600 bg-red-50 p-2.5 rounded border border-red-100">{{ submitError }}</p>

            <div class="flex justify-end">
              <button 
                type="submit" 
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg transition disabled:opacity-50"
                :disabled="actionLoading"
              >
                Post Reply
              </button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</template>
