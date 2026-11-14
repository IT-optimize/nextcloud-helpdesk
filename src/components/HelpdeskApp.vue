<!--
  - SPDX-FileCopyrightText: 2026 Co-Optimize
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcContent app-name="helpdesk">
		<NcAppNavigation :aria-label="t('helpdesk', 'Helpdesk')">
			<template #list>
				<NcAppNavigationItem :name="t('helpdesk', 'New request')"
					:active="route.view === 'new'"
					href="#/new">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem :name="t('helpdesk', 'My requests')"
					:active="route.scope === 'mine'"
					href="#/mine">
					<template #icon>
						<AccountIcon :size="20" />
					</template>
					<template v-if="openCount" #counter>
						<NcCounterBubble :count="openCount" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem v-if="page.isContact"
					:name="t('helpdesk', 'Everyone\'s requests')"
					:active="route.scope === 'all'"
					href="#/all">
					<template #icon>
						<AccountGroupIcon :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
			<template v-if="page.provider" #footer>
				<div class="helpdesk-contact">
					<span class="helpdesk-contact__label">{{ t('helpdesk', 'Your IT contact') }}</span>
					<strong>{{ page.provider.name }}</strong>
					<a v-if="page.provider.phone" :href="`tel:${page.provider.phone.replace(/[^\d+]/g, '')}`">{{ page.provider.phone }}</a>
				</div>
			</template>
		</NcAppNavigation>

		<NcAppContent :page-heading="t('helpdesk', 'Helpdesk')">
			<div class="helpdesk">
				<NcEmptyContent v-if="!page.provider && route.view === 'new'"
					:name="t('helpdesk', 'Helpdesk is not set up yet')"
					:description="page.isAdmin
						? t('helpdesk', 'Enter your IT provider in the settings, then everyone here can ask for help.')
						: t('helpdesk', 'Your administrator needs to enter who helps you with IT.')">
					<template #icon>
						<LifebuoyIcon />
					</template>
					<template v-if="page.isAdmin" #action>
						<NcButton variant="primary" :href="adminUrl">
							{{ t('helpdesk', 'Open the settings') }}
						</NcButton>
					</template>
				</NcEmptyContent>

				<template v-else-if="route.view === 'new'">
					<h1 class="helpdesk__title">
						{{ t('helpdesk', 'Get help with IT') }}
					</h1>
					<NewRequest :page="page" @sent="onSent" />
				</template>

				<template v-else-if="route.view === 'list'">
					<h1 class="helpdesk__title">
						{{ route.scope === 'all' ? t('helpdesk', 'Everyone\'s requests') : t('helpdesk', 'My requests') }}
					</h1>
					<RequestList :scope="route.scope" />
				</template>

				<RequestDetail v-else-if="route.view === 'request'"
					:id="route.id"
					:back-href="lastList"
					@changed="refreshCount" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroupOutline.vue'
import AccountIcon from 'vue-material-design-icons/AccountOutline.vue'
import LifebuoyIcon from 'vue-material-design-icons/Lifebuoy.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import NewRequest from './NewRequest.vue'
import RequestDetail from './RequestDetail.vue'
import RequestList from './RequestList.vue'
import { api } from '../api.js'

const page = loadState('helpdesk', 'page')
const adminUrl = generateUrl('/settings/admin/helpdesk')

// The page is small, so the address after # is the whole router:
// #/new, #/mine, #/all, #/request/12
function parse(hash) {
	const request = hash.match(/^#\/request\/(\d+)$/)
	if (request) {
		return { view: 'request', id: Number(request[1]) }
	}
	if (hash === '#/mine' || (hash === '#/all' && page.isContact)) {
		return { view: 'list', scope: hash.slice(2) }
	}
	return { view: 'new' }
}

const route = ref(parse(window.location.hash))
const lastList = ref('#/mine')
function onHashChange() {
	route.value = parse(window.location.hash)
	if (route.value.view === 'list') {
		lastList.value = '#/' + route.value.scope
	}
	if (route.value.view !== 'request') {
		refreshCount()
	}
}
window.addEventListener('hashchange', onHashChange)
onBeforeUnmount(() => window.removeEventListener('hashchange', onHashChange))

const mine = ref(page.requests)
const openCount = computed(() => mine.value.filter(r => r.status === 'open').length)
async function refreshCount() {
	try {
		mine.value = (await api.listRequests('mine')).requests
	} catch {
		// the counter just stays as it was
	}
}

function onSent(request) {
	mine.value.unshift(request)
	window.location.hash = `#/request/${request.id}`
}
</script>

<style scoped lang="scss">
.helpdesk {
	max-width: 1000px;
	margin: 0 auto;
	padding: 24px 16px 48px;

	:deep(h2) {
		font-size: 18px;
		font-weight: bold;
		margin: 0 0 12px;
	}

	.helpdesk__title {
		font-size: 24px;
		font-weight: bold;
		margin: 8px 0 24px;
	}

	// room for the navigation toggle in the corner when the page is narrow
	@media (max-width: 1100px) {
		padding-top: 48px;
	}
}

.helpdesk-contact {
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 12px 16px;

	&__label {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}

	a {
		color: var(--color-primary-element);
	}
}
</style>
