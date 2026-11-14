<!--
  - SPDX-FileCopyrightText: 2026 Co-Optimize
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="detail">
		<NcButton variant="tertiary" :href="backHref" class="detail__back">
			<template #icon>
				<ArrowLeftIcon :size="20" />
			</template>
			{{ t('helpdesk', 'Back to the list') }}
		</NcButton>

		<NcLoadingIcon v-if="!request && !failed" :size="32" />
		<NcEmptyContent v-else-if="failed" :name="failed">
			<template #icon>
				<InboxIcon />
			</template>
		</NcEmptyContent>

		<template v-else>
			<header class="detail__head">
				<div>
					<h2 class="detail__subject">
						{{ request.subject }}
					</h2>
					<RequestLabels :request="request" />
				</div>
				<NcButton v-if="request.status === 'open'" :disabled="busy" @click="setStatus('solved')">
					<template #icon>
						<CheckIcon :size="20" />
					</template>
					{{ t('helpdesk', 'Mark as solved') }}
				</NcButton>
				<NcButton v-else :disabled="busy" @click="setStatus('open')">
					<template #icon>
						<RestoreIcon :size="20" />
					</template>
					{{ t('helpdesk', 'Open again') }}
				</NcButton>
			</header>

			<ol class="timeline">
				<li class="item item--message">
					<div class="item__head">
						<strong>{{ request.name }}</strong>
						<span>{{ when(request.createdAt, language) }}</span>
						<span v-if="request.state === 'sent'">· {{ t('helpdesk', 'Sent to {name}', { name: request.providerName }) }}</span>
					</div>
					<p class="item__body">
						{{ request.body }}
					</p>
					<ul v-if="request.files.length" class="item__files">
						<li v-for="file in request.files" :key="file.name">
							<PaperclipIcon :size="16" />
							{{ file.name }}
						</li>
					</ul>
				</li>

				<li v-for="entry in history" :key="entry.id" :class="['item', `item--${entry.kind}`]">
					<template v-if="entry.kind === 'solved' || entry.kind === 'reopened'">
						<div class="item__event">
							<CheckIcon v-if="entry.kind === 'solved'" :size="16" />
							<RestoreIcon v-else :size="16" />
							{{ entry.kind === 'solved'
								? t('helpdesk', '{name} marked it as solved', { name: entry.name })
								: t('helpdesk', '{name} opened it again', { name: entry.name }) }}
							· {{ when(entry.createdAt, language) }}
						</div>
					</template>
					<template v-else>
						<div class="item__head">
							<strong>{{ entry.name }}</strong>
							<span>{{ when(entry.createdAt, language) }}</span>
							<span v-if="entry.kind === 'note'" class="item__kind">{{ t('helpdesk', 'Note, not sent to {name}', { name: request.providerName }) }}</span>
							<span v-else-if="entry.state === 'sent'">· {{ t('helpdesk', 'Sent to {name}', { name: request.providerName }) }}</span>
							<span v-else class="item__waiting">· {{ t('helpdesk', 'Waiting to send') }}</span>
						</div>
						<p class="item__body">
							{{ entry.body }}
						</p>
					</template>
				</li>
			</ol>

			<p class="detail__hint">
				{{ t('helpdesk', 'Answers from {name} come by email to {email}.', { name: request.providerName, email: request.replyEmail }) }}
			</p>

			<form class="composer" @submit.prevent>
				<NcTextArea v-model="text"
					:label="t('helpdesk', 'Write more')"
					:placeholder="request.status === 'solved'
						? t('helpdesk', 'Not solved after all? Write what is still wrong.')
						: t('helpdesk', 'New information, or a question')"
					rows="4"
					resize="vertical" />
				<div class="composer__actions">
					<NcButton :disabled="busy || !text.trim()" @click="addNote">
						<template #icon>
							<NoteIcon :size="20" />
						</template>
						{{ t('helpdesk', 'Save as note') }}
					</NcButton>
					<NcButton variant="primary" :disabled="busy || !text.trim()" @click="sendMessage">
						<template #icon>
							<NcLoadingIcon v-if="busy" :size="20" />
							<SendIcon v-else :size="20" />
						</template>
						{{ t('helpdesk', 'Send to {name}', { name: request.providerName }) }}
					</NcButton>
				</div>
				<p class="composer__hint">
					{{ t('helpdesk', 'Notes stay here: everyone who can see this request can read them, but they are not sent to {name}.', { name: request.providerName }) }}
				</p>
			</form>
		</template>
	</div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { getLanguage, t } from '@nextcloud/l10n'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import InboxIcon from 'vue-material-design-icons/InboxOutline.vue'
import NoteIcon from 'vue-material-design-icons/NoteTextOutline.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import SendIcon from 'vue-material-design-icons/Send.vue'
import RequestLabels from './RequestLabels.vue'
import { api, errorMessage, when } from '../api.js'

const props = defineProps({
	id: { type: Number, required: true },
	backHref: { type: String, default: '#/mine' },
})
const emit = defineEmits(['changed'])

const language = getLanguage()
const request = ref(null)
const history = ref([])
const failed = ref(null)
const text = ref('')
const busy = ref(false)

function show(result) {
	request.value = result.request
	history.value = result.history
}

async function load() {
	request.value = null
	failed.value = null
	text.value = ''
	try {
		show(await api.getRequest(props.id))
	} catch (error) {
		failed.value = errorMessage(error, t('helpdesk', 'Could not load the request'))
	}
}

watch(() => props.id, load, { immediate: true })

async function run(action, success) {
	busy.value = true
	try {
		show(await action())
		success?.()
		emit('changed')
		return true
	} catch (error) {
		showError(errorMessage(error, t('helpdesk', 'Could not save')))
		return false
	} finally {
		busy.value = false
	}
}

function setStatus(status) {
	run(() => api.setStatus(props.id, status))
}

function addNote() {
	run(() => api.addNote(props.id, text.value), () => { text.value = '' })
}

function sendMessage() {
	run(() => api.sendMessage(props.id, text.value), () => {
		text.value = ''
		const last = history.value[history.value.length - 1]
		if (last?.state === 'sent') {
			showSuccess(t('helpdesk', 'Sent to {name}', { name: request.value.providerName }))
		} else {
			showWarning(t('helpdesk', 'Saved. It is emailed as soon as possible; Helpdesk keeps trying.'))
		}
	})
}
</script>

<style scoped lang="scss">
.detail {
	&__back {
		margin-bottom: 8px;
	}

	&__head {
		display: flex;
		flex-wrap: wrap;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 16px;
	}

	.detail__subject {
		margin: 0 0 6px;
		font-size: 22px;
		font-weight: bold;
		overflow-wrap: anywhere;
	}

	&__hint {
		margin: 12px 0 20px;
		color: var(--color-text-maxcontrast);
	}
}

.timeline {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.item {
	padding: 12px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);

	&--note {
		// Notes stand out from what was sent, like internal notes in Ticket system.
		border-color: var(--color-warning);
		background: color-mix(in srgb, var(--color-warning) 12%, transparent);
	}

	&--solved, &--reopened {
		padding: 0 16px;
		border: none;
	}

	&__head {
		display: flex;
		flex-wrap: wrap;
		gap: 4px 8px;
		margin-bottom: 6px;
		color: var(--color-text-maxcontrast);

		strong {
			color: var(--color-main-text);
		}
	}

	&__kind {
		font-weight: bold;
		color: var(--color-warning-text);
	}

	&__waiting {
		color: var(--color-warning-text);
	}

	&__body {
		white-space: pre-wrap;
		overflow-wrap: anywhere;
	}

	&__files {
		margin-top: 8px;

		li {
			display: flex;
			align-items: center;
			gap: 6px;
			color: var(--color-text-maxcontrast);
		}
	}

	&__event {
		display: flex;
		align-items: center;
		gap: 6px;
		color: var(--color-text-maxcontrast);
	}
}

.composer {
	display: flex;
	flex-direction: column;
	gap: 8px;

	&__actions {
		display: flex;
		flex-wrap: wrap;
		justify-content: flex-end;
		gap: 8px;
	}

	&__hint {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}
}
</style>
