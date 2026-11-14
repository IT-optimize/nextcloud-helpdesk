<!--
  - SPDX-FileCopyrightText: 2026 Co-Optimize
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="request-list">
		<div class="request-list__filters" role="group" :aria-label="t('helpdesk', 'Show')">
			<NcButton v-for="option in filters"
				:key="option.id"
				:variant="status === option.id ? 'primary' : 'secondary'"
				:aria-pressed="status === option.id"
				@click="status = option.id">
				{{ option.name }}
			</NcButton>
		</div>

		<NcLoadingIcon v-if="loading && !requests.length" :size="32" class="request-list__loading" />
		<NcEmptyContent v-else-if="!requests.length"
			:name="status === 'solved' ? t('helpdesk', 'No solved requests') : status === 'open' ? t('helpdesk', 'No open requests') : t('helpdesk', 'No requests yet')">
			<template #icon>
				<InboxIcon />
			</template>
		</NcEmptyContent>
		<ul v-else>
			<li v-for="request in requests" :key="request.id">
				<a class="row" :href="`#/request/${request.id}`">
					<span class="row__main">
						<span class="row__subject">{{ request.subject }}</span>
						<RequestLabels :request="request" />
					</span>
					<span class="row__meta">
						<span v-if="scope === 'all'">{{ request.name }} · </span>
						<span>{{ t('helpdesk', 'Last activity {time}', { time: when(request.updatedAt, language) }) }}</span>
					</span>
				</a>
			</li>
		</ul>
	</div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { getLanguage, t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import InboxIcon from 'vue-material-design-icons/InboxOutline.vue'
import RequestLabels from './RequestLabels.vue'
import { api, errorMessage, when } from '../api.js'

const props = defineProps({
	scope: { type: String, default: 'mine' },
})

const language = getLanguage()
const filters = [
	{ id: 'open', name: t('helpdesk', 'Open') },
	{ id: 'solved', name: t('helpdesk', 'Solved') },
	{ id: '', name: t('helpdesk', 'All') },
]
const status = ref('open')
const requests = ref([])
const loading = ref(false)

async function load() {
	loading.value = true
	try {
		requests.value = (await api.listRequests(props.scope, status.value)).requests
	} catch (error) {
		showError(errorMessage(error, t('helpdesk', 'Could not load the requests')))
	} finally {
		loading.value = false
	}
}

watch([() => props.scope, status], load, { immediate: true })
</script>

<style scoped lang="scss">
.request-list {
	&__filters {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		margin-bottom: 12px;
	}

	&__loading {
		margin-top: 40px;
	}
}

.row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 4px 16px;
	padding: 12px 8px;
	border-bottom: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	color: inherit;

	&:hover, &:focus-visible {
		background: var(--color-background-hover);
	}

	&__main {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px;
		min-width: 0;
	}

	&__subject {
		font-weight: bold;
		overflow-wrap: anywhere;
	}

	&__meta {
		color: var(--color-text-maxcontrast);
	}
}
</style>
