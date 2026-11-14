# Changelog

All notable changes to Helpdesk (app id `helpdesk`). The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/).

## 1.0.0 – Unreleased

### Added
- A Helpdesk page for everyone in the Nextcloud: the IT provider as contact (name, phone, email, website, opening hours, a note), and a form to ask for help with a subject, a description, "urgent", files or screenshots (up to 5, 10 MB together) and the address the answer goes to.
- My requests: each person's requests, open or solved, with their history. Open a request to write more (emailed to the provider in the same thread, with the same retries; never before the request itself), add a note that stays in the company, or mark it solved or open it again. Writing on a solved request opens it again. Marking it solved does not email the provider.
- An "IT contact" group, chosen by an admin: its members see everyone's requests ("Everyone's requests") and can write in them and mark them solved. Admins are not included on their own.
- Contact people for each provider (name, role, phone, email), shown on the contact card. In a locked setup they come from `config.php` (`contacts`), which a start script in your image can write.
- Requests are stored first and then emailed to the provider. One that cannot be sent is tried again (after 5 minutes, 15 minutes, 1 hour, then every 3 hours); after three failed tries the admins get a notification, which disappears once it is sent. Admins can try again at once and send a test request.
- Administration settings → Helpdesk: a list of providers, one of which receives the requests.
- A provider set in `config.php` (`'helpdesk' => ['provider' => [...]]`) locks the receiver: the settings show it read-only and nothing can send elsewhere, even when the entry is incomplete.
- Request emails come from the Nextcloud's own address with the person in Reply-To, carry an `X-Nextcloud-Helpdesk` header and end with "Sent with Helpdesk for Nextcloud", so Ticket system for Nextcloud files them under the person who wrote them.
- Danish and English.
