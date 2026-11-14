# Helpdesk for Nextcloud

One place for everyone in your Nextcloud to ask for IT help. The people who
help you with IT (your provider) are shown as the contact, and every request
goes to them by email.

- **The contact**: name, phone, email, website, opening hours, a note, and the
  people there (name, role, phone, email).
- **Asking for help**: what it is about, what happens, "urgent", files or
  screenshots (up to 5, 10 MB together), and where the answer should go.
- **My requests**: each person sees their requests, open or solved, and what
  happened on each one. They can write more (emailed to the provider in the
  same thread), add a note that stays in the company, and mark a request solved
  or open it again. Writing on a solved request opens it again.
- **IT contact in the company**: the members of one group an admin chooses (for
  example the office manager) see everyone's requests and can do the same.
  Admins are not included on their own, because requests can be personal.
- **Nothing gets lost**: requests and messages are saved before they are
  emailed. If the email cannot be sent, Helpdesk tries again (after 5 minutes,
  15 minutes, 1 hour, then every 3 hours), the person sees "Waiting to send",
  and after three failed tries the admins get a notification. A message never
  goes out before its request.
- **One receiver at a time**: admins keep a list of providers and choose which
  one receives the requests.
- **Answers come by email**, straight to the person who asked. Helpdesk does not
  hear back from the provider, so "solved" is set by the people in the company;
  marking a request solved does not email the provider.
- **Danish and English.**

Targets **Nextcloud 34–35**.

## Setting it up

Administration settings → Helpdesk:

1. **Add provider**: name and the email address requests go to; phone, website,
   opening hours, a note and contact people are optional. Choose which provider
   receives the requests, and save.
2. **IT contact in your company** (optional): the group whose members see
   everyone's requests.
3. **Send a test request** to see that it arrives.

Requests are sent with this Nextcloud's email server (Administration settings →
Basic settings), from its own address, with the person who asked in Reply-To.
Background jobs should run with Cron so waiting requests are retried on time.

## For providers: lock the receiver

A Nextcloud partner or IT provider that delivers Nextcloud to customers can
make sure requests always reach them: put the provider in the server's
`config.php`.

```php
'helpdesk' => [
  'provider' => [
    'name' => 'Your IT company',
    'email' => 'support@your-it-company.example',
    'phone' => '+45 12 34 56 78',
    'website' => 'https://your-it-company.example',
    'hours' => 'Mon–Fri 8–16',
    'note' => 'Call if the whole office is down.',
    'contacts' => [
      ['name' => 'Anna', 'role' => 'Technician', 'phone' => '+45 11 22 33 44', 'email' => 'anna@your-it-company.example'],
    ],
  ],
],
```

or with `occ`:

```sh
occ config:system:set helpdesk provider name --value="Your IT company"
occ config:system:set helpdesk provider email --value="support@your-it-company.example"
```

As soon as `config.php` names a provider, the receiver is locked: the settings
show it read-only, and the app sends nowhere else. An incomplete entry (no name
or no valid email) keeps it locked; requests then wait until the entry is
fixed, and the settings say so. Only someone with access to the server's files
can change it.

In an image you deliver, a start script can write these values on every start,
for example from environment variables such as `HELPDESK_PROVIDER_NAME`,
`HELPDESK_PROVIDER_EMAIL`, `HELPDESK_PROVIDER_PHONE`, `HELPDESK_PROVIDER_WEBSITE`,
`HELPDESK_PROVIDER_HOURS` and `HELPDESK_PROVIDER_NOTE`, and contact people from
`HELPDESK_PROVIDER_CONTACT_1` … `_10` (`"Name|Role|Phone|Email"`, only the name
is required), so an update or a change on the server cannot drop them.

## With Ticket system for Nextcloud

[Ticket system](https://github.com/IT-optimize/nextcloud-ticketsystem) is the companion app for the provider's side.
Requests carry an `X-Nextcloud-Helpdesk` header and end with the line
"Sent with Helpdesk for Nextcloud". Ticket system recognises them and makes
the ticket for the person in Reply-To instead of the sending Nextcloud's
address, so the provider answers the right person. Messages written after the
request refer to it (In-Reply-To / References) and join the same ticket as
replies from that person. Any other helpdesk that
reads email works too; the person's name, email, Nextcloud user and server are
at the bottom of every request.

## Installing

Download the latest `helpdesk-x.y.z.tar.gz` from the
[releases](https://github.com/IT-optimize/nextcloud-helpdesk/releases), unpack it
into your Nextcloud's `custom_apps` folder so that
`custom_apps/helpdesk/appinfo/info.xml` exists, and run `occ app:enable helpdesk`.
After updating to a new version, run `occ upgrade`.

## Working on the code

PHP is in `lib/`, the interface (Vue 3 + @nextcloud/vue) in `src/`, built into
`js/` (committed) by `npm ci && npm run build`. Tests: `composer install &&
composer test` and `npm test`. `scripts/release.sh` builds the release archive.
Security problems: see [SECURITY.md](SECURITY.md).

## Licence

AGPL-3.0-or-later, © Co-Optimize. See [LICENSE](LICENSE).
