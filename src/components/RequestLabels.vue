<!--
  - SPDX-FileCopyrightText: 2026 Co-Optimize
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span class="labels">
		<span v-if="request.status === 'solved'" class="label label--solved">{{ t('helpdesk', 'Solved') }}</span>
		<span v-else class="label label--open">{{ t('helpdesk', 'Open') }}</span>
		<span v-if="request.urgent && request.status !== 'solved'" class="label label--urgent">{{ t('helpdesk', 'Urgent') }}</span>
		<span v-if="request.state !== 'sent'"
			class="label label--waiting"
			:title="t('helpdesk', 'Helpdesk keeps trying until the email goes through.')">
			{{ t('helpdesk', 'Waiting to send') }}
		</span>
	</span>
</template>

<script setup>
import { t } from '@nextcloud/l10n'

defineProps({
	request: { type: Object, required: true },
})
</script>

<style scoped lang="scss">
.labels {
	display: inline-flex;
	flex-wrap: wrap;
	gap: 6px;
}

.label {
	padding: 1px 8px;
	border-radius: var(--border-radius-pill);
	font-size: var(--font-size-small, 13px);
	font-weight: normal;
	white-space: nowrap;

	// The theme's pale status colours, with their matching dark text (as in Ticket system).
	&--open {
		background: var(--color-primary-element-light);
		color: var(--color-primary-element-light-text);
	}

	&--solved {
		background: var(--color-success);
		color: var(--color-success-text, #000);
	}

	&--waiting {
		background: var(--color-warning);
		color: var(--color-warning-text, #000);
	}

	&--urgent {
		background: var(--color-error);
		color: var(--color-error-text, #fff);
	}
}
</style>
