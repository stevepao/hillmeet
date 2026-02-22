<?php
/**
 * terms.php
 * Purpose: Terms of Service page (legal).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
$pageTitle = 'Terms of Service';
$content = ob_start();
?>
<div class="legal-page" style="max-width:720px; margin:0 auto;">
  <h1>Terms of Service</h1>
  <p class="muted"><strong>Effective date:</strong> February 19, 2026</p>
  <?php
  $companyName = \Hillmeet\Support\config('legal.company_name', 'Your Company, LLC');
  $companyType = \Hillmeet\Support\config('legal.company_type', 'a limited liability company');
  $supportEmail = \Hillmeet\Support\config('legal.support_email', 'support@example.com');
  $governingState = \Hillmeet\Support\config('legal.governing_state', 'Oregon');
  $privacyUrl = \Hillmeet\Support\url('/privacy');
  ?>
  <p>Hillmeet is operated by <strong><?= \Hillmeet\Support\e($companyName) ?></strong>, <?= \Hillmeet\Support\e($companyType) ?> (“we,” “us,” or “our”).</p>
  <p>These Terms of Service (“Terms”) govern your access to and use of the Hillmeet website, applications, and related services (collectively, the “Service”). By accessing or using the Service, you agree to these Terms.</p>
  <p>If you do not agree, do not use the Service.</p>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">1. The Service</h2>
    <p>Hillmeet helps people schedule meetings by creating polls to find mutually available times, then locking a final time and sending meeting invitations.</p>
    <p>Hillmeet may support:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Signing in with Google or using an email + one-time PIN mechanism;</li>
      <li>Optionally connecting Google Calendar to check free/busy availability and create calendar events when you request it;</li>
      <li>Sending .ics calendar files by email as meeting invitations.</li>
    </ul>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">2. Eligibility</h2>
    <p>You must be at least 13 years old to use the Service.</p>
    <p>If you are using the Service on behalf of an organization, you represent that you have authority to bind that organization to these Terms.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">3. Accounts and Authentication</h2>
    <p>To use the Service, you may need to create an account or authenticate. Hillmeet may offer:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Google sign-in; and/or</li>
      <li>Email + one-time PIN verification.</li>
    </ul>
    <p>You are responsible for maintaining the confidentiality of your account access and for all activity that occurs under your account.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">4. Your Content and Responsibilities</h2>
    <p>The Service allows you to create and share poll content such as titles, descriptions, locations, time zones, time options, and invitations to participants.</p>
    <p>You are responsible for:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>The content you create and share through the Service;</li>
      <li>Ensuring you have the right to invite participants and share information with them;</li>
      <li>Complying with all applicable laws and regulations;</li>
      <li>Using the Service only with parties who are known to each other and in contexts where such invitations are appropriate (for example, not for mass unsolicited outreach).</li>
    </ul>
    <p>Hillmeet does not review or pre-approve content, and we do not guarantee that content is accurate or suitable for any particular purpose.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">5. Acceptable Use</h2>
    <p>You agree not to use the Service to:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Harass, threaten, defame, or abuse others;</li>
      <li>Send spam, bulk unsolicited invitations, or phishing messages;</li>
      <li>Promote illegal activities or upload unlawful content;</li>
      <li>Interfere with or disrupt the Service, including attempting to bypass security, rate limits, or access controls.</li>
    </ul>
    <p>We may suspend or terminate access if we believe you have violated these Terms. (See Section 11.)</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">6. Calendar Integrations and Third-Party Services</h2>
    <h3 style="font-size:1rem; margin-top:var(--space-3);">a. Google Calendar (Optional)</h3>
    <p>If you choose to connect Google Calendar, you authorize Hillmeet to access Google Calendar data only as described in our <a href="<?= \Hillmeet\Support\e($privacyUrl) ?>">Privacy Policy</a>—for example, to check free/busy availability for poll time slots and to create calendar events only when you explicitly request it.</p>
    <p>You can disconnect Google Calendar in the app, and you can also revoke access through your Google Account settings.</p>
    <h3 style="font-size:1rem; margin-top:var(--space-3);">b. Third-Party Services</h3>
    <p>The Service may depend on or interoperate with third-party services (such as Google Calendar and email delivery providers). We do not control these third parties, and we are not responsible for their availability, performance, security practices, or actions.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">7. Email and Notifications</h2>
    <p>By using the Service, you agree that Hillmeet may send you service-related emails, including:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>One-time sign-in PINs;</li>
      <li>Poll invitations you initiate;</li>
      <li>Final time notifications and .ics attachments when a poll is locked.</li>
    </ul>
    <p>You are responsible for ensuring you have consent from participants you invite and that your invitations comply with applicable laws (including anti-spam and privacy laws).</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">8. No Guarantee of Participant Responses or Behavior (Scheduling Disclaimer)</h2>
    <p>Hillmeet facilitates scheduling, but <strong>we do not guarantee</strong> that:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>Participants will respond to a poll;</li>
      <li>Participants will accept calendar invitations;</li>
      <li>Participants will attend a meeting; or</li>
      <li>Participants will behave in any particular way.</li>
    </ul>
    <p>You are responsible for coordinating with participants and confirming attendance and logistics as needed.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">9. Service Availability; Changes to the Service</h2>
    <p>The Service is provided on an “AS IS” and “AS AVAILABLE” basis. We do not guarantee uninterrupted operation.</p>
    <p>We may modify, suspend, or discontinue any part of the Service at any time, including introducing new features, changing limits, or removing features.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">10. Fees (Currently Free)</h2>
    <p>The Service is currently provided at no charge. We may introduce paid features or plans in the future. If we do, we will provide notice before any charges apply.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">11. Suspension and Termination</h2>
    <p>We may suspend or terminate your access to the Service at any time if:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>You violate these Terms;</li>
      <li>Your use creates risk or possible legal exposure for Hillmeet or others; or</li>
      <li>We are required to do so by law.</li>
    </ul>
    <p>You may stop using the Service at any time. Account and data deletion are handled as described in our <a href="<?= \Hillmeet\Support\e($privacyUrl) ?>">Privacy Policy</a>.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">12. Intellectual Property</h2>
    <p>The Service, including its software, design, and branding, is owned by <?= \Hillmeet\Support\e($companyName) ?> or its licensors and is protected by intellectual property laws.</p>
    <p>You retain ownership of content you submit, subject to the limited license below.</p>
    <p><strong>License to Operate the Service.</strong> You grant Hillmeet a non-exclusive, worldwide, royalty-free license to host, store, transmit, and display your content <strong>only as necessary to operate and improve the Service</strong>, including sending emails and invitations you initiate.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">13. Disclaimers</h2>
    <p>To the maximum extent permitted by law:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>We disclaim all warranties, express or implied;</li>
      <li>We do not warrant that the Service will meet your requirements or that scheduling results will be accurate or conflict-free;</li>
      <li>We do not warrant that emails, notifications, or calendar invitations will be delivered or processed by third-party providers.</li>
    </ul>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">14. Limitation of Liability</h2>
    <p>To the maximum extent permitted by law, <?= \Hillmeet\Support\e($companyName) ?> and its officers, members, employees, and contractors will not be liable for any indirect, incidental, special, consequential, or punitive damages.</p>
    <p>Our total liability for any claim relating to the Service will not exceed the greater of:</p>
    <ul style="margin:var(--space-2) 0; padding-left:1.25rem;">
      <li>$100 USD; or</li>
      <li>The amount you paid to use the Service in the 12 months before the claim (if any).</li>
    </ul>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">15. Privacy</h2>
    <p>Our <a href="<?= \Hillmeet\Support\e($privacyUrl) ?>">Privacy Policy</a> describes how we collect, use, and handle your information, including Google Calendar data (if you connect it).</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">16. Governing Law; Venue</h2>
    <p>These Terms are governed by the laws of the State of <?= \Hillmeet\Support\e($governingState) ?>, without regard to conflict of laws principles.</p>
    <p>Any dispute arising out of or relating to these Terms or the Service will be brought in the state or federal courts located in <?= \Hillmeet\Support\e($governingState) ?>.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">17. Changes to These Terms</h2>
    <p>We may update these Terms from time to time. If we make material changes, we will update the Effective date. Continued use of the Service after changes become effective constitutes acceptance of the updated Terms.</p>
  </section>

  <section style="margin-top:var(--space-5);">
    <h2 style="font-size:1.125rem; margin-bottom:var(--space-2);">18. Contact</h2>
    <p>Questions about these Terms? Contact us at: <a href="mailto:<?= \Hillmeet\Support\e($supportEmail) ?>"><?= \Hillmeet\Support\e($supportEmail) ?></a></p>
  </section>

  <p class="muted" style="margin-top:var(--space-6); font-size:var(--text-sm);">© 2026 Hillwork, LLC. All rights reserved.</p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
