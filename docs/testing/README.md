# BPLD AEMS manual testing pack

Open [the PDF guide](AEMS_BPLD_MANUAL_TESTING_GUIDE.pdf) for the special-engagement-to-CMS testing journey. The [Markdown source](AEMS_BPLD_MANUAL_TESTING_GUIDE.md) contains the editable field values, expected outcomes and run sheet. The HTML copy provides searchable browser access.

Use **Oxanna S. Custodio (BPLD-HEAD)** as the main BPLD auditee. Her login-page demo card fills the credentials; click Sign in afterwards. The existing account and Maria's separate BPLD account are preserved.

Upload fixtures when prompted by the guide:

- [Permit register](fixtures/BPLD-UAT-permit-register.csv): 60 fictional transactions.
- [Sample results](fixtures/BPLD-UAT-sample-results.csv): 12 samples, with three deliberately missing reconciliation sign-offs.
- [Test authority and SOP](fixtures/BPLD-UAT-criteria-and-authority.txt): invented software-test criteria, not an official directive.
- [Management response](fixtures/BPLD-UAT-management-response.txt): fictional response for the auditee workflow.

Use the actual test date for **T** and calculate relative dates as instructed. The guide's September 8 examples are illustrative; evidence-obtained dates must not be in the future.

Regenerate the PDF, HTML and CSV fixtures from the repository root:

```powershell
node scripts/build-aems-manual.mjs
```

The generator uses the repository's Playwright dependency and installed Microsoft Edge. It does not create audit records or modify the application database. Preview PNGs are documentation review artifacts.

The pack is a test specification. Authentication and automated checks do not establish that the entire manual audit journey has passed; fill in the run sheet during execution.
