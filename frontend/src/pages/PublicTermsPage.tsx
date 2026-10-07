import PublicDocumentPage, { type PublicDocumentSection } from './PublicDocumentPage'

const sections: PublicDocumentSection[] = [
  {
    title: '1. Introduction and acceptance',
    paragraphs: [
      'These terms describe use of the Mercury Managed demonstration platform. By accessing or using the site, you agree to these terms. If you do not agree, do not use the demo.',
    ],
  },
  {
    title: '2. Demonstration environment',
    paragraphs: [
      'This platform is provided for demonstration and presentation purposes. Accounts, balances, deposits, withdrawals, strategies, performance figures, and market history are simulated or administrator-configured examples. The platform does not execute live trades or transfer cryptocurrency.',
      'Do not send cryptocurrency or other funds to addresses displayed in this demo. Demo wallet values are configuration examples and are not instructions to make a payment.',
    ],
  },
  {
    title: '3. Account responsibilities',
    paragraphs: [
      'You are responsible for information submitted to your account and for keeping your sign-in credentials confidential. Use only accounts you are authorized to access, and notify the operator through a contact channel supplied for this instance if you believe your account has been accessed improperly.',
    ],
  },
  {
    title: '4. Simulated deposits and withdrawals',
    paragraphs: [
      'Deposit and withdrawal screens demonstrate review workflows and may show pending, approved, confirmed, or rejected states. Those states affect only simulated records in this environment; they do not represent receipt, custody, transfer, or payout of real assets.',
    ],
  },
  {
    title: '5. Strategies and performance',
    paragraphs: [
      'Strategy descriptions, tier qualification, account performance, and historical snapshots illustrate configurable product behavior. They are not investment advice, trade instructions, or records of actual investment results.',
      'Past or simulated performance does not guarantee future results. No return, profit, or outcome is promised.',
    ],
  },
  {
    title: '6. Market data and pricing',
    paragraphs: [
      'Prices, percentage changes, and historical charts are simulated, seeded, or configured for the demo. They are not live exchange quotations and may not reflect any external market.',
    ],
  },
  {
    title: '7. Acceptable use',
    paragraphs: [
      'Do not misuse the site, attempt to gain unauthorized access, disrupt its operation, submit unlawful or harmful content, or misrepresent demo output as real financial activity.',
    ],
  },
  {
    title: '8. Suspension and termination',
    paragraphs: [
      'The operator of a particular instance may restrict or suspend access to protect the service, respond to misuse, or maintain the demonstration environment. Demo accounts and records may be changed or removed as the environment is maintained.',
    ],
  },
  {
    title: '9. Limitation of liability',
    paragraphs: [
      'The demo is provided for informational and evaluation purposes on an “as is” and “as available” basis. To the extent permitted by applicable law, the operator is not responsible for decisions made in reliance on simulated output, interruption or unavailability of the demo, or loss arising from use of the demo.',
    ],
  },
  {
    title: '10. Changes to these terms',
    paragraphs: [
      'These terms may be updated as the demonstration changes. The current version will be published on this page; continued use after an update constitutes acceptance of the revised terms.',
    ],
  },
  {
    title: '11. Contact',
    paragraphs: [
      'No public support email address or contact endpoint is configured for this demo. Please use a contact channel supplied separately by the team operating your instance.',
    ],
  },
]

function PublicTermsPage() {
  return (
    <PublicDocumentPage
      title="Terms of Use"
      intro="These terms apply to your use of this demonstration platform. They explain the simulated nature of the experience and the responsibilities that come with using an account."
      updated="Last updated: October 7, 2026"
      sections={sections}
    />
  )
}

export default PublicTermsPage
