<?php
/**
 * Privacy Policy — refined for Hillmeet.
 */
$pageTitle = 'Privacy Policy';
$content = ob_start();
?>
<div class="legal-page" style="max-width:720px; margin:0 auto;">
  <h1>Privacy Policy</h1>
  <p class="muted">Last updated: February 19, 2026</p>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">1. Introduction</h2>
    <p>Hillmeet (“we”, “us”, or “our”) helps people schedule meetings by creating polls to find mutually available times, then locking a final time and sending calendar invitations. We are committed to protecting your privacy and being transparent about how we collect, use, and store your information.</p>
    <p>This Privacy Policy explains what data we collect, how we use it, and your choices.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">2. Information We Collect</h2>

    <h3 style="font-size:1rem; margin-top:var(--space-3);">a. Information You Provide Directly</h3>
    <p>Depending on how you use the app, we may collect:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Email address</li>
      <li>Name (if provided, e.g. when signing in with Google or email)</li>
      <li>Poll details you create: title, description, location, time zone, and time options</li>
      <li>Your availability responses (yes / maybe / no per time slot)</li>
      <li>Email addresses of people you invite to a poll (when you send invitations)</li>
      <li>Your timezone preference (optional, for displaying times in notifications)</li>
      <li>Authentication information, such as:
        <ul style="margin-top:0.25rem;">
          <li>Google account sign-in</li>
          <li>Email + one-time PIN verification</li>
        </ul>
      </li>
    </ul>
    <p>We also store session data so you stay signed in, and we may log certain actions (e.g. creating a poll, sending invites) with an identifier and IP address for security and operational purposes. IP addresses are not used for tracking or profiling.</p>

    <h3 style="font-size:1rem; margin-top:var(--space-3);">b. Google Account Information (Optional)</h3>
    <p>If you choose to sign in with Google, we receive your email, name, and profile picture from Google to create or update your account. If you separately connect Google Calendar, we request access only with your explicit consent for:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Your calendar free/busy availability (to show when you’re busy for poll time slots)</li>
      <li>The ability to create calendar events on your behalf (e.g. when you lock a poll and choose to add the event to Google Calendar)</li>
    </ul>
    <p>We store the calendar identifiers (IDs) you select for availability checks so we can query only those calendars in the future; we also store calendar names (summaries) so you can recognize them in the app. You choose which of your calendars to include (e.g. primary or others); we do not access any calendar you have not selected. We do not store event content from your calendars (such as event titles or descriptions) when checking free/busy. <strong>Free/busy caching:</strong> We cache free/busy results for up to 10 minutes to reduce repeated Google API calls. Cached free/busy data is associated with the requesting user and poll and is not used after it expires.</p>
    <p>Google Calendar access is governed by the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer">Google API Services User Data Policy</a>.</p>

    <h3 style="font-size:1rem; margin-top:var(--space-3);">c. Non-Google Users</h3>
    <p>You do not need a Google account to use the app. If you do not connect a Google account:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>You may authenticate via email and a one-time PIN</li>
      <li>You may receive .ics calendar files by email when a poll is locked</li>
      <li>No calendar data is accessed via Google APIs</li>
    </ul>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">3. How We Use Your Information</h2>
    <p>We use your information to:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Identify and authenticate users</li>
      <li>Store and display polls, time options, and availability responses</li>
      <li>Determine overlapping free/busy times when you have connected Google Calendar</li>
      <li>Create calendar invitations or events only when you request it</li>
      <li>Send scheduling-related emails (e.g. poll invitations, final time notifications with .ics attachments)</li>
      <li>Send one-time sign-in PINs to your email</li>
    </ul>
    <p>We do not:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Sell your data</li>
      <li>Use your data for advertising</li>
      <li>Use Google user data for purposes unrelated to scheduling</li>
    </ul>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">4. Google User Data Use and Disclosure</h2>
    <p>If you connect your Google account for Calendar:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Google Calendar data is used only to check availability (free/busy) and to create calendar events when you explicitly request it.</li>
      <li>This data is not shared with third parties.</li>
      <li>Free/busy cache is retained for up to 10 minutes, keyed per user and per poll time slot; cached data is not used after it expires.</li>
    </ul>
    <p><strong>Calendar events we create:</strong> We create a calendar event only when you lock a poll and explicitly choose “Add to Google Calendar” (or equivalent) and confirm. When we do, we write only these fields: event title (from the poll title), description (from the poll description), location (from the poll location), start and end time (the locked time), and attendee email addresses (only if you choose to add participants). We do not write any other data to your calendar.</p>
    <p>We comply with the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">5. Data Retention</h2>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Poll and availability data is retained until you delete the poll or your account. You may delete polls at any time from the app.</li>
      <li>Authentication and account data (email, name, session, Google OAuth tokens if connected) is retained for as long as your account is active. You may request account and data deletion by contacting us.</li>
      <li>One-time PINs expire after a short period and are not retained for longer than necessary.</li>
    </ul>

    <h3 style="font-size:1rem; margin-top:var(--space-4); margin-bottom:var(--space-2);">Disconnecting Google Calendar</h3>
    <p>You can disconnect Google Calendar at any time from <strong>Calendar settings</strong> in the app (Disconnect button). When you disconnect, we immediately delete our stored copies: OAuth tokens, your calendar selections (IDs and names), and cached free/busy data. If you revoke Hillmeet’s access in <strong>Google Account → Security → Third-party access</strong> instead, we can no longer access your Google data; you may then use the Disconnect button in the app to remove our stored copies, or request removal by contacting us.</p>

    <h3 style="font-size:1rem; margin-top:var(--space-4); margin-bottom:var(--space-2);">Account deletion</h3>
    <p>When you request account deletion and we delete your account, we delete all data associated with it, including: your profile (email, name), polls you created, your votes and participation, OAuth tokens, stored calendar selections, and cached free/busy data. Events we created on your Google Calendar are not removed by us (they remain in your calendar); you can delete those in Google Calendar if you wish.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">6. Data Security</h2>
    <p>We use reasonable technical and organizational measures to protect your data, including:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Encryption in transit (HTTPS)</li>
      <li>Encryption at rest for sensitive data (e.g. Google OAuth tokens stored in encrypted form)</li>
      <li>Audit logging of certain operations (e.g. account and poll actions) for security and operational purposes</li>
    </ul>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">7. Your Choices</h2>
    <p>You can:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Use the app without a Google account (sign in with email and PIN)</li>
      <li>Use the app without connecting Google Calendar (receive .ics by email instead)</li>
      <li>Disconnect Google Calendar in the app (Calendar settings → Disconnect) to remove our stored tokens, calendar selections, and free/busy cache. You can also revoke Hillmeet’s access in <strong>Google Account → Security → Third-party access</strong>; we will then no longer be able to read your availability or create events until you reconnect.</li>
      <li>Delete individual polls at any time</li>
      <li>Request deletion of your account and associated data by contacting us</li>
    </ul>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">8. Children’s Privacy</h2>
    <p>Hillmeet is not intended for children under 13, and we do not knowingly collect personal data from children.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">9. Service Providers</h2>
    <p>We use trusted service providers (for example, hosting and email delivery) to operate the service. They may process limited personal data on our behalf under contractual confidentiality and security obligations.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">10. Changes to This Policy</h2>
    <p>We may update this policy from time to time. Material changes will be posted on this page with an updated “Last updated” date.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">11. Contact Us</h2>
    <?php $supportEmail = \Hillmeet\Support\config('legal.support_email', 'support@example.com'); ?>
    <p>If you have questions about this Privacy Policy or your data, contact us at: <a href="mailto:<?= \Hillmeet\Support\e($supportEmail) ?>"><?= \Hillmeet\Support\e($supportEmail) ?></a></p>
  </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
