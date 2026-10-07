import PublicDocumentPage, { type PublicDocumentSection } from './PublicDocumentPage'

const sections: PublicDocumentSection[] = [
  {
    title: '1. Information collected',
    paragraphs: [
      'The application stores information you provide when registering or managing your profile, which may include your name, email address, country, and optional phone number.',
      'The application also stores authentication and account-security data needed to operate sign-in and recovery, such as password hashes, email-verification state, session and CSRF cookies, and password-reset records.',
    ],
  },
  {
    title: '2. Simulated account and activity records',
    paragraphs: [
      'The demo may store account balances, tier assignments, simulated deposits and withdrawals, transaction history, market-price snapshots, performance history, support requests, audit records, and notifications associated with your account.',
      'These records demonstrate application workflows. They do not represent real funds, trading, custody, or blockchain activity.',
    ],
  },
  {
    title: '3. How information is used',
    paragraphs: [
      'Information is used to provide account access, display the demo experience, process simulated workflows, support account and administrative functions, protect the application, and communicate account or workflow status through in-app notifications and configured verification or recovery messages.',
    ],
  },
  {
    title: '4. Security practices',
    paragraphs: [
      'The application uses password hashing, session-based authentication, CSRF protections, authorization checks, and request throttling in relevant flows. No method of storage or transmission can be represented as completely secure.',
    ],
  },
  {
    title: '5. Notifications and communications',
    paragraphs: [
      'The platform may display in-app notifications related to account status and simulated activity. Verification and password-reset messages depend on the mail configuration of the instance. No advertising or marketing tracking service is described by this demo notice.',
    ],
  },
  {
    title: '6. Cookies and session technologies',
    paragraphs: [
      'Sign-in uses first-party session and CSRF cookie mechanisms to maintain authentication and protect requests. These technologies support application functionality rather than advertising.',
    ],
  },
  {
    title: '7. Data retention',
    paragraphs: [
      'Account and demo activity records remain in the instance database while the operator maintains them. This demonstration does not publish a fixed retention period; records may be removed when accounts or the local/demo environment are reset or maintained.',
    ],
  },
  {
    title: '8. Your choices and requests',
    paragraphs: [
      'You may update profile information through available account settings. For questions or requests about information associated with an account, contact the team operating your instance through a channel it has provided. No public contact address or request endpoint is configured on this demo site.',
    ],
  },
  {
    title: '9. Changes to this notice',
    paragraphs: [
      'This notice may change as the demo evolves. Updates will be published on this page with a revised update date.',
    ],
  },
]

function PublicPrivacyPage() {
  return (
    <PublicDocumentPage
      title="Privacy Notice"
      intro="This notice describes the information handled by the Mercury Managed demonstration application and how it supports the account and simulated activity features."
      updated="Last updated: October 7, 2026"
      sections={sections}
    />
  )
}

export default PublicPrivacyPage
