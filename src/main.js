/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import '@nextcloud/dialogs/style.css'
import { createApp } from 'vue'
import HelpdeskApp from './components/HelpdeskApp.vue'

createApp(HelpdeskApp).mount('#helpdesk-app')
