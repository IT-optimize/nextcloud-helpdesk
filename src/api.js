/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const url = (path) => generateOcsUrl('/apps/helpdesk/api/v1' + path)
const data = (response) => response.data.ocs.data

/** The server's message (already translated) or the fallback. */
export function errorMessage(error, fallback) {
	return error?.response?.data?.ocs?.data?.message || fallback
}

export const api = {
	/** @param {'mine'|'all'} scope @param {''|'open'|'solved'} status */
	listRequests: (scope = 'mine', status = '') => axios.get(url('/requests'), { params: { scope, status } }).then(data),
	getRequest: (id) => axios.get(url(`/requests/${id}`)).then(data),
	/** @param {{subject: string, body: string, urgent: boolean, replyEmail: string}} fields @param {File[]} files */
	sendRequest: (fields, files) => {
		const form = new FormData()
		for (const [key, value] of Object.entries(fields)) {
			form.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value)
		}
		for (const file of files) {
			form.append('files[]', file)
		}
		return axios.post(url('/requests'), form).then(data)
	},
	sendMessage: (id, body) => axios.post(url(`/requests/${id}/messages`), { body }).then(data),
	addNote: (id, body) => axios.post(url(`/requests/${id}/notes`), { body }).then(data),
	setStatus: (id, status) => axios.put(url(`/requests/${id}/status`), { status }).then(data),
	getSettings: () => axios.get(url('/settings')).then(data),
	saveSettings: (providers, activeId) => axios.put(url('/settings'), { providers, activeId }).then(data),
	saveContactGroup: (group) => axios.put(url('/settings/contact-group'), { group }).then(data),
	retryUnsent: () => axios.post(url('/settings/retry')).then(data),
	sendTest: (replyEmail) => axios.post(url('/settings/test'), { replyEmail }).then(data),
}

/** A date and time in the user's language. */
export function when(seconds, language) {
	return new Date(seconds * 1000).toLocaleString(language, { dateStyle: 'medium', timeStyle: 'short' })
}
