<!--
  - SPDX-FileCopyrightText: 2026 Co-Optimize
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="helpdesk-admin">
		<NcSettingsSection :name="t('helpdesk', 'Who receives the requests')"
			:description="t('helpdesk', 'Requests from everyone in this Nextcloud are emailed to one receiver: your IT provider. They are shown as the contact on the Helpdesk page.')">
			<template v-if="state.locked">
				<NcNoteCard type="info">
					{{ t('helpdesk', 'Your provider has set the receiver in the server configuration, so it cannot be changed here.') }}
				</NcNoteCard>
				<ProviderCard v-if="state.active" :provider="state.active" class="helpdesk-admin__card" />
				<NcNoteCard v-else type="error">
					{{ t('helpdesk', 'The receiver in the server configuration is incomplete: it needs a name and a valid email address. Requests are kept until it is fixed.') }}
				</NcNoteCard>
			</template>

			<template v-else>
				<p v-if="!providers.length" class="helpdesk-admin__empty">
					{{ t('helpdesk', 'Add the company or person who helps you with IT.') }}
				</p>
				<fieldset v-for="(provider, i) in providers" :key="provider.key" class="helpdesk-admin__provider">
					<legend class="hidden-visually">
						{{ provider.name || t('helpdesk', 'New provider') }}
					</legend>
					<div class="helpdesk-admin__provider-head">
						<NcCheckboxRadioSwitch :model-value="activeKey"
							:value="provider.key"
							type="radio"
							name="helpdesk-active"
							@update:model-value="v => activeKey = v">
							{{ t('helpdesk', 'Receives the requests') }}
						</NcCheckboxRadioSwitch>
						<NcButton variant="tertiary" @click="remove(i)">
							<template #icon>
								<DeleteIcon :size="20" />
							</template>
							{{ t('helpdesk', 'Remove') }}
						</NcButton>
					</div>
					<div class="helpdesk-admin__grid">
						<NcTextField v-model="provider.name" :label="t('helpdesk', 'Name')" required />
						<NcTextField v-model="provider.email" type="email" :label="t('helpdesk', 'Email for requests')" required />
						<NcTextField v-model="provider.phone" type="tel" :label="t('helpdesk', 'Phone (optional)')" />
						<NcTextField v-model="provider.website" :label="t('helpdesk', 'Website (optional)')" />
						<NcTextField v-model="provider.hours" :label="t('helpdesk', 'Opening hours (optional)')" :placeholder="t('helpdesk', 'For example: Mon–Fri 8–16')" />
					</div>
					<NcTextArea v-model="provider.note"
						:label="t('helpdesk', 'Note shown to everyone (optional)')"
						:placeholder="t('helpdesk', 'For example: Call if the whole office is down')"
						resize="vertical" />
					<div class="helpdesk-admin__people">
						<h4>{{ t('helpdesk', 'Contact people (optional)') }}</h4>
						<p class="helpdesk-admin__help">
							{{ t('helpdesk', 'Shown on the contact card. Requests still go to the email for requests above.') }}
						</p>
						<div v-for="(person, j) in provider.contacts" :key="j" class="helpdesk-admin__person">
							<NcTextField v-model="person.name" :label="t('helpdesk', 'Name')" />
							<NcTextField v-model="person.role" :label="t('helpdesk', 'Role (optional)')" :placeholder="t('helpdesk', 'For example: Technician')" />
							<NcTextField v-model="person.phone" type="tel" :label="t('helpdesk', 'Phone (optional)')" />
							<NcTextField v-model="person.email" type="email" :label="t('helpdesk', 'Email (optional)')" />
							<NcButton variant="tertiary"
								:aria-label="t('helpdesk', 'Remove {name}', { name: person.name || t('helpdesk', 'contact person') })"
								@click="provider.contacts.splice(j, 1)">
								<template #icon>
									<DeleteIcon :size="20" />
								</template>
							</NcButton>
						</div>
						<NcButton variant="tertiary" @click="provider.contacts.push({ name: '', role: '', phone: '', email: '' })">
							<template #icon>
								<PlusIcon :size="20" />
							</template>
							{{ t('helpdesk', 'Add contact person') }}
						</NcButton>
					</div>
				</fieldset>
				<div class="helpdesk-admin__row">
					<NcButton @click="add">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						{{ t('helpdesk', 'Add provider') }}
					</NcButton>
					<NcButton variant="primary" :disabled="saving" @click="save">
						{{ t('helpdesk', 'Save') }}
					</NcButton>
				</div>
			</template>
		</NcSettingsSection>

		<NcSettingsSection :name="t('helpdesk', 'IT contact in your company')"
			:description="t('helpdesk', 'Everyone sees their own requests. The members of this group (for example the office manager) also see everyone\'s requests, and can write in them and mark them solved. Admins are not included on their own, because requests can be personal.')">
			<div class="helpdesk-admin__row helpdesk-admin__row--start">
				<NcSelect v-model="contactGroup"
					:options="state.groups"
					label="name"
					:reduce="g => g.id"
					:input-label="t('helpdesk', 'Group')"
					:placeholder="t('helpdesk', 'Nobody')"
					:clearable="true"
					class="helpdesk-admin__group"
					@update:model-value="saveGroup" />
			</div>
		</NcSettingsSection>

		<NcSettingsSection :name="t('helpdesk', 'Delivery')"
			:description="t('helpdesk', 'Requests use this Nextcloud\'s email server (Basic settings). A request that cannot be sent is kept and tried again; after three failed tries the admins get a notification.')">
			<p v-if="state.unsent" class="helpdesk-admin__unsent">
				{{ n('helpdesk', '{count} request is waiting to be sent.', '{count} requests are waiting to be sent.', state.unsent, { count: state.unsent }) }}
				<NcButton :disabled="busy" @click="retry">
					{{ t('helpdesk', 'Try again now') }}
				</NcButton>
			</p>
			<p v-else class="helpdesk-admin__ok">
				{{ t('helpdesk', 'Every request has been sent.') }}
			</p>
			<div class="helpdesk-admin__row helpdesk-admin__row--start">
				<NcTextField v-model="testEmail"
					type="email"
					:label="t('helpdesk', 'Your email, for the answer')"
					class="helpdesk-admin__test-email" />
				<NcButton :disabled="busy || !state.active || !testEmail.trim()" @click="test">
					{{ t('helpdesk', 'Send a test request') }}
				</NcButton>
			</div>
		</NcSettingsSection>
	</div>
</template>

<script setup>
import { ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { n, t } from '@nextcloud/l10n'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ProviderCard from './ProviderCard.vue'
import { api, errorMessage } from '../api.js'

const initial = loadState('helpdesk', 'settings')
const state = ref(initial)
const providers = ref([])
const activeKey = ref(null)
const saving = ref(false)
const busy = ref(false)
const testEmail = ref(initial.myEmail ?? '')
const contactGroup = ref(null)

let nextKey = 0
// Each row gets a local key, so a new provider can be chosen before it has an id.
function load(serverState) {
	state.value = { ...state.value, ...serverState }
	contactGroup.value = serverState.contactGroup ?? null
	providers.value = (serverState.providers ?? []).map(p => ({
		...p,
		contacts: (p.contacts ?? []).map(c => ({ ...c })),
		key: 'k' + nextKey++,
	}))
	const active = providers.value.find(p => p.id === (serverState.active?.id ?? serverState.activeId))
	activeKey.value = active?.key ?? providers.value[0]?.key ?? null
}
load(initial)

function add() {
	const provider = { id: '', name: '', email: '', phone: '', website: '', hours: '', note: '', contacts: [], key: 'k' + nextKey++ }
	providers.value.push(provider)
	activeKey.value ??= provider.key
}

function remove(i) {
	const [gone] = providers.value.splice(i, 1)
	if (gone.key === activeKey.value) {
		activeKey.value = providers.value[0]?.key ?? null
	}
}

async function save() {
	saving.value = true
	try {
		// New providers get an id here, so the active one can be named before the server has seen it.
		const index = providers.value.findIndex(p => p.key === activeKey.value)
		const list = providers.value.map(({ key, ...p }) => p)
		const ids = list.map(p => p.id || Math.random().toString(36).slice(2, 12).replace(/[^a-z0-9]/g, '0'))
		const result = await api.saveSettings(list.map((p, i) => ({ ...p, id: ids[i] })), index >= 0 ? ids[index] : null)
		load(result)
		showSuccess(t('helpdesk', 'Saved'))
	} catch (error) {
		showError(errorMessage(error, t('helpdesk', 'Could not save')))
	} finally {
		saving.value = false
	}
}

async function saveGroup(group) {
	try {
		const result = await api.saveContactGroup(group ?? '')
		state.value = { ...state.value, ...result }
		contactGroup.value = result.contactGroup ?? null
		showSuccess(t('helpdesk', 'Saved'))
	} catch (error) {
		showError(errorMessage(error, t('helpdesk', 'Could not save')))
	}
}

async function retry() {
	busy.value = true
	try {
		const result = await api.retryUnsent()
		state.value = { ...state.value, unsent: result.unsent }
		if (result.unsent) {
			showWarning(n('helpdesk', '{count} request could still not be sent. Check the email server settings.', '{count} requests could still not be sent. Check the email server settings.', result.unsent, { count: result.unsent }))
		} else {
			showSuccess(t('helpdesk', 'Every request has been sent.'))
		}
	} catch (error) {
		showError(errorMessage(error, t('helpdesk', 'Could not send')))
	} finally {
		busy.value = false
	}
}

async function test() {
	busy.value = true
	try {
		const result = await api.sendTest(testEmail.value)
		state.value = { ...state.value, unsent: result.unsent }
		if (result.request.state === 'sent') {
			showSuccess(t('helpdesk', 'Test request sent to {name}', { name: result.request.providerName }))
		} else {
			showWarning(t('helpdesk', 'The test request could not be emailed. Check the email server settings; Helpdesk keeps trying.'))
		}
	} catch (error) {
		showError(errorMessage(error, t('helpdesk', 'Could not send')))
	} finally {
		busy.value = false
	}
}
</script>

<style scoped lang="scss">
.helpdesk-admin {
	&__card {
		max-width: 480px;
		margin-top: 12px;
	}

	&__empty, &__ok {
		color: var(--color-text-maxcontrast);
	}

	&__provider {
		display: flex;
		flex-direction: column;
		gap: 12px;
		max-width: 800px;
		margin-bottom: 16px;
		padding: 12px 16px 16px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
	}

	&__provider-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
	}

	&__grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
		gap: 12px;
		align-items: start;
	}

	&__row {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		max-width: 800px;

		&--start {
			justify-content: flex-start;
			margin-top: 12px;
		}
	}

	&__test-email, &__group {
		max-width: 320px;
		width: 100%;
	}

	.helpdesk-admin__people h4 {
		margin: 4px 0 0;
		font-size: var(--default-font-size, 15px);
		font-weight: bold;
	}

	&__help {
		margin-bottom: 8px;
		color: var(--color-text-maxcontrast);
	}

	&__person {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
		gap: 8px;
		align-items: end;
		margin-bottom: 8px;

		@media (max-width: 900px) {
			grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
		}
	}

	&__unsent {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 12px;
		color: var(--color-warning-text);
	}
}
</style>
