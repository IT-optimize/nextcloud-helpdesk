<!--
  - SPDX-FileCopyrightText: 2026 Co-Optimize
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<section class="provider" :aria-label="t('helpdesk', 'Your IT contact')">
		<div class="provider__head">
			<LifebuoyIcon :size="28" class="provider__icon" />
			<div>
				<div class="provider__label">
					{{ t('helpdesk', 'Your IT contact') }}
				</div>
				<h2 class="provider__name">
					{{ provider.name }}
				</h2>
			</div>
		</div>
		<ul class="provider__lines">
			<li v-if="provider.phone">
				<PhoneIcon :size="18" />
				<a :href="`tel:${provider.phone.replace(/[^\d+]/g, '')}`">{{ provider.phone }}</a>
			</li>
			<li>
				<EmailIcon :size="18" />
				<a :href="`mailto:${provider.email}`">{{ provider.email }}</a>
			</li>
			<li v-if="provider.website">
				<WebIcon :size="18" />
				<a :href="provider.website" target="_blank" rel="noopener noreferrer">{{ shortUrl(provider.website) }}</a>
			</li>
			<li v-if="provider.hours">
				<ClockIcon :size="18" />
				<span>{{ provider.hours }}</span>
			</li>
		</ul>
		<p v-if="provider.note" class="provider__note">
			{{ provider.note }}
		</p>
		<ul v-if="provider.contacts?.length" class="provider__people" :aria-label="t('helpdesk', 'People')">
			<li v-for="(person, i) in provider.contacts" :key="i" class="person">
				<div class="person__name">
					<AccountIcon :size="18" />
					<strong>{{ person.name }}</strong>
					<span v-if="person.role" class="person__role">{{ person.role }}</span>
				</div>
				<a v-if="person.phone" :href="`tel:${person.phone.replace(/[^\d+]/g, '')}`">{{ person.phone }}</a>
				<a v-if="person.email" :href="`mailto:${person.email}`">{{ person.email }}</a>
			</li>
		</ul>
	</section>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import AccountIcon from 'vue-material-design-icons/AccountOutline.vue'
import ClockIcon from 'vue-material-design-icons/ClockOutline.vue'
import EmailIcon from 'vue-material-design-icons/EmailOutline.vue'
import LifebuoyIcon from 'vue-material-design-icons/Lifebuoy.vue'
import PhoneIcon from 'vue-material-design-icons/Phone.vue'
import WebIcon from 'vue-material-design-icons/Web.vue'

defineProps({
	provider: { type: Object, required: true },
})

const shortUrl = (url) => url.replace(/^https?:\/\//, '').replace(/\/$/, '')
</script>

<style scoped lang="scss">
.provider {
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);

	&__head {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 12px;
	}

	&__icon {
		color: var(--color-primary-element);
	}

	&__label {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}

	&__name {
		margin: 0;
		font-size: 20px;
		font-weight: bold;
	}

	&__lines {
		display: flex;
		flex-direction: column;
		gap: 8px;

		li {
			display: flex;
			align-items: center;
			gap: 8px;
			min-width: 0;
		}

		a {
			color: var(--color-primary-element);
			overflow-wrap: anywhere;
		}
	}

	&__note {
		margin-top: 12px;
		color: var(--color-text-maxcontrast);
		white-space: pre-line;
	}

	&__people {
		display: flex;
		flex-direction: column;
		gap: 10px;
		margin-top: 12px;
		padding-top: 12px;
		border-top: 1px solid var(--color-border);
	}
}

.person {
	display: flex;
	flex-direction: column;
	gap: 2px;

	&__name {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 6px;
	}

	&__role {
		color: var(--color-text-maxcontrast);
	}

	a {
		padding-inline-start: 24px;
		color: var(--color-primary-element);
		overflow-wrap: anywhere;
	}
}
</style>
