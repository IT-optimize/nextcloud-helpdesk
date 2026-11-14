<!--
  - SPDX-FileCopyrightText: 2026 Co-Optimize
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="new-request">
		<form class="new-request__form" @submit.prevent="send">
			<h2>{{ t('helpdesk', 'Describe the problem') }}</h2>
			<NcTextField v-model="form.subject"
				:label="t('helpdesk', 'What is it about?')"
				:placeholder="t('helpdesk', 'For example: The printer does not print')"
				maxlength="200"
				required />
			<NcTextArea v-model="form.body"
				:label="t('helpdesk', 'What happens, and what have you tried?')"
				rows="7"
				resize="vertical"
				required />
			<NcCheckboxRadioSwitch v-model="form.urgent" type="switch">
				{{ t('helpdesk', 'Urgent: I cannot work') }}
			</NcCheckboxRadioSwitch>
			<NcTextField v-model="form.replyEmail"
				type="email"
				:label="t('helpdesk', 'Send the answer to')"
				required />

			<div>
				<input ref="fileInput"
					type="file"
					multiple
					class="hidden-visually"
					@change="addFiles">
				<NcButton variant="tertiary" @click="fileInput.click()">
					<template #icon>
						<PaperclipIcon :size="20" />
					</template>
					{{ t('helpdesk', 'Attach files or screenshots') }}
				</NcButton>
				<ul v-if="files.length" class="new-request__files">
					<li v-for="(file, i) in files" :key="i">
						<span>{{ file.name }} ({{ size(file.size) }})</span>
						<NcButton variant="tertiary-no-background"
							:aria-label="t('helpdesk', 'Remove {name}', { name: file.name })"
							@click="files.splice(i, 1)">
							<template #icon>
								<CloseIcon :size="18" />
							</template>
						</NcButton>
					</li>
				</ul>
				<p v-if="filesProblem" class="new-request__problem">
					{{ filesProblem }}
				</p>
			</div>

			<div class="new-request__actions">
				<NcButton type="submit" variant="primary" :disabled="!canSend">
					<template #icon>
						<NcLoadingIcon v-if="sending" :size="20" />
						<SendIcon v-else :size="20" />
					</template>
					{{ t('helpdesk', 'Send to {name}', { name: page.provider.name }) }}
				</NcButton>
			</div>
		</form>

		<aside class="new-request__side">
			<ProviderCard :provider="page.provider" />
		</aside>
	</div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import SendIcon from 'vue-material-design-icons/Send.vue'
import ProviderCard from './ProviderCard.vue'
import { api, errorMessage } from '../api.js'

const props = defineProps({
	page: { type: Object, required: true },
})
const emit = defineEmits(['sent'])

const form = reactive({ subject: '', body: '', urgent: false, replyEmail: props.page.me.email })
const files = ref([])
const fileInput = ref(null)
const sending = ref(false)

const filesProblem = computed(() => {
	if (files.value.length > props.page.maxFiles) {
		return t('helpdesk', 'At most {count} files', { count: props.page.maxFiles })
	}
	if (files.value.reduce((sum, f) => sum + f.size, 0) > props.page.maxBytes) {
		return t('helpdesk', 'The files are too large together (at most {size} MB)', { size: props.page.maxBytes / 1024 / 1024 })
	}
	return null
})
const canSend = computed(() => !sending.value && form.subject.trim() && form.body.trim() && form.replyEmail.trim() && !filesProblem.value)

function addFiles(event) {
	files.value.push(...event.target.files)
	event.target.value = ''
}

async function send() {
	sending.value = true
	try {
		const { request } = await api.sendRequest({ ...form }, files.value)
		if (request.state === 'sent') {
			showSuccess(t('helpdesk', 'Your request was sent to {name}', { name: request.providerName }))
		} else {
			showWarning(t('helpdesk', 'Your request is saved. It could not be emailed yet; Helpdesk keeps trying.'))
		}
		Object.assign(form, { subject: '', body: '', urgent: false })
		files.value = []
		emit('sent', request)
	} catch (error) {
		showError(errorMessage(error, t('helpdesk', 'Could not send your request')))
	} finally {
		sending.value = false
	}
}

const size = (bytes) => bytes < 1024 * 1024
	? `${Math.max(1, Math.round(bytes / 1024))} KB`
	: `${(bytes / 1024 / 1024).toFixed(1)} MB`
</script>

<style scoped lang="scss">
.new-request {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 320px;
	gap: 24px;
	align-items: start;

	@media (max-width: 900px) {
		grid-template-columns: minmax(0, 1fr);

		.new-request__side {
			order: -1;
		}
	}

	&__form {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	&__files li {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		padding-inline-start: 12px;
	}

	&__problem {
		color: var(--color-error-text);
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
	}
}
</style>
