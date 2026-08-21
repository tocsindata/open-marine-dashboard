@php
$content = [
    'about' => [
        'title' => 'About Marine',
        'intro' => 'Marine is a free, privacy-conscious operations workspace designed to help boaters keep vessel information, maintenance, tasks, voyages, costs, documents, and source-aware marine information organized in one place.',
        'sections' => [
            ['heading' => 'Why Marine exists', 'paragraphs' => ['Boaters often spread important records across paper binders, calendars, spreadsheets, phones, charting tools, and memory. Marine brings those operational records together without pretending to replace the specialized equipment, official publications, or judgement required for safe navigation.', 'The initial geographic focus is British Columbia and the west coast of Canada. Vessel records, maintenance, tasks, budgets, documents, and logs are designed to remain useful anywhere. Regional marine-data coverage will expand only when an authoritative source, its licensing terms, and its operational limitations have been verified.']],
            ['heading' => 'What we believe', 'items' => ['Unknown is better than wrong. Missing or stale information must be identified honestly.', 'Private information—including precise locations, documents, voyage plans, and finances—should remain private by default.', 'A useful boating tool should still work when an external feed is unavailable.', 'Safety-sensitive information must show its source and freshness.', 'Core access is free: no subscriptions, advertisements, premium locks, or artificial vessel limits.']],
            ['heading' => 'Who operates the service', 'paragraphs' => ['Marine is a Tocsin Data project. The application is being developed as a practical marine operations platform, beginning with a secure Laravel foundation and expanding through tested, source-verified modules. Product changes are recorded on the What’s New page.']],
        ],
    ],
    'features' => [
        'title' => 'Features',
        'intro' => 'Marine is being built as a complete operational record for recreational and working boaters, with every feature designed around clear ownership, privacy, and honest source status.',
        'sections' => [
            ['heading' => 'Available foundation', 'items' => ['Free account registration and email verification', 'Private, owner-scoped vessel profiles', 'General and vessel-specific tasks', 'Responsive dashboard and public information pages', 'Audit groundwork for security- and ownership-sensitive actions', 'Terminal-only super-administrator management with no web trigger']],
            ['heading' => 'Vessel operations', 'items' => ['Registration, insurance, surveys, licences, and expiry reminders', 'Equipment inventory, engine hours, maintenance schedules, work orders, parts, receipts, and service history', 'Budgets, recurring costs, expenses, vendors, and operational reports', 'Captain, fuel, voyage, safety, maintenance, and incident logs', 'Private document storage with authorization and retention controls']],
            ['heading' => 'Voyages and marine information', 'items' => ['Waypoints, routes, voyage plans, float plans, checklists, and track history', 'Opt-in location tracking with accuracy, retention, and sharing controls', 'Official weather, warning, tide, current, earthquake, tsunami, and navigation-notice integrations after approval', 'Map layers with source, time, unit, datum, attribution, and stale-state information', 'Low-connectivity and offline behavior that never presents cached safety data as current']],
            ['heading' => 'No paid tier', 'paragraphs' => ['Marine is free to register and use. The product does not include subscriptions, trials, advertisements, upgrade prompts, or premium-only safety features. External services that create unavoidable third-party costs will not be activated silently; any future cost-related decision will be disclosed before it affects users.']],
        ],
    ],
    'getting-started' => [
        'title' => 'Getting Started',
        'intro' => 'You can begin with a free account and a basic vessel record. Marine is designed to become more useful as you add information, not to require a complete inventory before you can use it.',
        'sections' => [
            ['heading' => '1. Create and secure your account', 'paragraphs' => ['Register with an email address you control, choose a unique password, accept the current Terms and Privacy Notice, and verify your email. Keep your login private and sign out of shared devices.']],
            ['heading' => '2. Add your vessel', 'paragraphs' => ['Start with the vessel name and type. Add dimensions, registration information, home port, engines, equipment, emergency contacts, documents, and expiry dates as they become available. Sensitive identifiers and uploaded records are intended to remain private.']],
            ['heading' => '3. Build your operating record', 'items' => ['Create tasks for immediate work.', 'Record engine hours and maintenance intervals.', 'Add registration, insurance, and safety-equipment expiry dates.', 'Record fuel, costs, voyages, incidents, and service work.', 'Review due and overdue items on the dashboard.']],
            ['heading' => '4. Verify before relying', 'paragraphs' => ['Marine is an organizational aid. Always compare weather, warnings, tides, currents, routes, and chart information with current official sources and required onboard publications. An unavailable or stale feed must be treated as unavailable—not as evidence that conditions are safe.']],
        ],
    ],
    'faq' => [
        'title' => 'Frequently Asked Questions',
        'intro' => 'These answers describe the service as it is intended to operate. Features that are not yet enabled remain visibly unavailable rather than being represented by simulated live information.',
        'sections' => [
            ['heading' => 'Is Marine free?', 'paragraphs' => ['Yes. Registration and core use are free. There are no subscriptions, premium plans, advertisements, or artificial vessel limits.']],
            ['heading' => 'Does Marine replace nautical charts or navigation equipment?', 'paragraphs' => ['No. Marine is not an approved navigation system and must never be your sole source for navigation, weather, warnings, tides, currents, or emergency decisions. Carry and use the charts, publications, equipment, and communications required for your vessel and voyage.']],
            ['heading' => 'Who can see my information?', 'paragraphs' => ['Your account and vessel information are private by default. Access may be given to people you deliberately invite, according to the role and duration you select. Administrators do not receive casual unrestricted access to private documents, precise locations, or budgets.']],
            ['heading' => 'Is location tracking always on?', 'paragraphs' => ['No. Tracking is off by default. When available, it must be deliberately enabled, visibly indicated, stoppable, and governed by device, retention, precision, and sharing controls.']],
            ['heading' => 'What happens when an external source fails?', 'paragraphs' => ['Marine will show the source as delayed, stale, unavailable, or unapproved. It will not reuse an old value without showing its age, and it will not substitute invented data. Manual vessel records and tasks can continue to work.']],
            ['heading' => 'Can I export or delete my data?', 'paragraphs' => ['The service policy provides for authenticated access, correction, export, and deletion requests. Some security or audit information may need to be retained or anonymized, and backup copies expire through controlled rotation rather than disappearing instantly.']],
        ],
    ],
    'regions' => [
        'title' => 'Supported Regions',
        'intro' => 'Marine’s first regional data programme is focused on the west coast of Canada, including British Columbia and the Salish Sea.',
        'sections' => [
            ['heading' => 'Region-independent features', 'paragraphs' => ['Accounts, vessel profiles, equipment, tasks, maintenance, budgets, documents, logs, and manually entered voyages are not inherently limited to British Columbia. Users remain responsible for entering accurate local information and following the rules of the jurisdiction where they operate.']],
            ['heading' => 'Regional marine-data coverage', 'paragraphs' => ['Weather areas, warnings, tide and current stations, navigational notices, charts, emergency guidance, and geographic datasets depend on official products and licensing. A region is listed as operational only after its source coverage, update behavior, attribution, and limitations have been verified.']],
            ['heading' => 'Expansion standard', 'items' => ['Use authoritative sources whenever available.', 'Record geographic and temporal coverage.', 'Respect licence, attribution, caching, and redistribution limits.', 'Show station, forecast area, datum, units, timestamps, and freshness.', 'Keep unsupported areas visibly unsupported rather than filling gaps with assumptions.']],
        ],
    ],
    'safety' => [
        'title' => 'Safety and Navigation Disclaimer',
        'intro' => 'Marine is an organizational and informational aid. It is not an approved navigation system, emergency service, watchkeeping service, or substitute for competent seamanship.',
        'sections' => [
            ['heading' => 'Do not rely on Marine alone', 'paragraphs' => ['Never use Marine as your sole source for position, route selection, collision avoidance, water depth, clearance, weather, warnings, tides, currents, hazards, Notices to Mariners, or emergency action. Use current official nautical charts and publications, required onboard equipment, direct observations, local notices, and authoritative broadcasts.']],
            ['heading' => 'Information can be delayed or wrong', 'paragraphs' => ['External data can be delayed, incomplete, corrected, cancelled, unavailable, outside its forecast area, or affected by communication and device failures. User-entered information can also be inaccurate. A displayed timestamp is not a guarantee that conditions have not changed.']],
            ['heading' => 'Your responsibilities', 'items' => ['Plan and conduct voyages according to applicable law and conditions.', 'Carry and maintain required charts, publications, safety equipment, and communications.', 'Confirm position and conditions through independent appropriate means.', 'Account for vessel draft, air draft, loading, equipment limitations, crew capability, and changing conditions.', 'Contact official emergency services directly when assistance is required.']],
            ['heading' => 'Official Canadian chart obligations', 'paragraphs' => ['Canadian Hydrographic Service guidance notes that Canadian navigation-safety rules require the carriage and use of nautical charts and publications in applicable circumstances. Marine does not satisfy that obligation merely by displaying a map or link.']],
        ],
        'links' => [['Canadian Hydrographic Service chart FAQ', 'https://www.charts.gc.ca/help-aide/faq-eng.html'], ['Canadian Coast Guard', 'https://www.canada.ca/en/canadian-coast-guard.html']],
    ],
    'data-sources' => [
        'title' => 'Data Sources and Attribution',
        'intro' => 'Marine treats provenance as part of the information. Operational data is enabled only after the provider, product, licence, geographic coverage, timing, units, and correction behavior have been reviewed.',
        'sections' => [
            ['heading' => 'Source families under review', 'items' => ['Environment and Climate Change Canada and the Meteorological Service of Canada for marine forecasts, warnings, and observations', 'Canadian Hydrographic Service and Fisheries and Oceans Canada for tides, currents, water levels, stations, and licensed chart products', 'Canadian Coast Guard for Notices to Mariners and navigational warnings', 'Natural Resources Canada for earthquake information', 'Official federal and British Columbia authorities for tsunami and emergency alerts', 'Legally usable map, port, marina, and geographic datasets']],
            ['heading' => 'What Marine records', 'paragraphs' => ['Where the source supplies it, Marine records the provider identifier, product, station or area, original units, normalized units, issued time, effective time, expiry time, observation or prediction time, retrieval time, update/correction relationship, and attribution. A value may be stored without being displayed as current if it has expired or the source has degraded.']],
            ['heading' => 'Licensing and charts', 'paragraphs' => ['Canadian Hydrographic Service products and data may be protected by Crown copyright and may require a licence. Marine will not copy, cache, or redistribute protected chart information merely because it is technically accessible. Map and chart layers must retain their required attribution and use restrictions.']],
            ['heading' => 'Current operational state', 'paragraphs' => ['No source should be considered live solely because it appears in this list. The System and Source Status page identifies which integrations are approved, active, delayed, disabled, or unavailable.']],
        ],
        'links' => [['ECCC and MSC GeoMet', 'https://eccc-msc.github.io/open-data/msc-geomet/readme_en/'], ['Canadian Hydrographic Service', 'https://www.charts.gc.ca/'], ['Canadian Coast Guard Notices to Mariners', 'https://www.notmar.gc.ca/'], ['CHS copyright and licensing', 'https://www.charts.gc.ca/copyright-droitdauteur/index-eng.html']],
    ],
    'status' => [
        'title' => 'System and Source Status',
        'intro' => 'This page explains how to interpret availability. A working website does not mean every external marine source is current or approved.',
        'sections' => [
            ['heading' => 'Application services', 'paragraphs' => ['Account, vessel, and task availability is measured separately from maps, mail, queues, storage, and external feeds. If a supporting service is degraded, affected features must say so directly.']],
            ['heading' => 'Source states', 'items' => ['Operational: approved, recently retrieved, and within its expected freshness window.', 'Delayed: data is older than expected but has not reached its expiry rule.', 'Stale: data is too old to present as current.', 'Unavailable: retrieval or processing failed, or the provider is unreachable.', 'Disabled: integration is intentionally turned off.', 'Unapproved: technical work may exist, but licensing, authority, or production approval is incomplete.']],
            ['heading' => 'Current foundation status', 'paragraphs' => ['Marine’s account and basic vessel/task foundation is under active development. External operational marine feeds must remain disabled until their individual approval and verification gates are satisfied. No demo reading should be interpreted as live conditions.']],
            ['heading' => 'Reporting a problem', 'paragraphs' => ['When reporting a service problem, include the page, approximate time, action attempted, and non-sensitive error text. Do not include passwords, private documents, precise location history, authentication links, or API credentials.']],
        ],
    ],
    'support' => [
        'title' => 'Contact and Support',
        'intro' => 'Support covers accounts, records, application behavior, accessibility, privacy requests, and suspected security problems. It is not an emergency-monitoring or dispatch service.',
        'sections' => [
            ['heading' => 'Before contacting support', 'items' => ['Check the System and Source Status page.', 'Record the time and exact action that failed.', 'Use a screenshot only after removing private or location-sensitive information.', 'Never send a password, recovery code, session cookie, private key, or API token.']],
            ['heading' => 'Account and privacy requests', 'paragraphs' => ['Use an authenticated in-application request whenever possible so ownership can be verified. Access, correction, export, deletion, and privacy complaints require identity verification proportionate to the request. Marine will not disclose account information merely because a requester knows an email address or vessel name.']],
            ['heading' => 'Security reports', 'paragraphs' => ['Potential vulnerabilities should follow the Security and Vulnerability Reporting page. Do not test against other users, download private data, degrade service, or publicly disclose a vulnerability before there has been a reasonable opportunity to investigate.']],
            ['heading' => 'Emergencies', 'paragraphs' => ['Do not use a support request for distress, overdue vessels, navigational hazards requiring immediate action, or threats to life or safety. Contact the appropriate official authority directly.']],
        ],
        'links' => [['Tocsin Data', 'https://tocsindata.com'], ['Canadian Coast Guard MCTS contact guidance', 'https://www.canada.ca/en/canadian-coast-guard/corporate/contact-us/contact-marine-communications-traffic-services.html']],
    ],
    'accessibility' => [
        'title' => 'Accessibility Statement',
        'intro' => 'Marine is intended to be usable with a keyboard, screen reader, magnification, touch input, reduced motion, and a wide range of screen sizes. The target is WCAG 2.2 Level AA.',
        'sections' => [
            ['heading' => 'Design commitments', 'items' => ['Semantic headings, landmarks, labels, and table structure', 'Visible keyboard focus and logical focus order', 'Text alternatives for meaningful images and icons', 'Sufficient colour contrast and information that does not rely on colour alone', 'Responsive layouts that support zoom and reflow', 'Reduced-motion support', 'Accessible validation and status messages', 'List and tabular alternatives for essential map information']],
            ['heading' => 'Known challenges', 'paragraphs' => ['Interactive maps, dense weather charts, track visualizations, and third-party widgets can create accessibility barriers. Marine must provide an equivalent textual or tabular route to operational information and must not make a map the only way to complete a critical task.']],
            ['heading' => 'Feedback', 'paragraphs' => ['If a feature prevents access, report the page, task, assistive technology and browser if comfortable doing so, and the format or accommodation that would help. Accessibility feedback is treated as product work, not as a reason to exclude a user.']],
        ],
    ],
    'security' => [
        'title' => 'Security and Vulnerability Reporting',
        'intro' => 'Marine may hold identity information, vessel records, precise locations, voyage plans, documents, and financial records. Responsible reports that help protect users are welcome.',
        'sections' => [
            ['heading' => 'Report safely', 'items' => ['Describe the affected URL or component and the observed behavior.', 'Use your own account and data.', 'Provide minimal reproduction steps and redact secrets.', 'Do not include private documents or precise locations in the initial report.', 'Stop if testing could expose another person’s information, alter data, or impair service.']],
            ['heading' => 'Prohibited testing', 'items' => ['Denial of service or excessive automated traffic', 'Social engineering, phishing, or physical attacks', 'Accessing, changing, retaining, or publishing another user’s data', 'Malware distribution or persistence', 'Testing third-party providers without their permission', 'Public disclosure before a reasonable investigation and remediation period']],
            ['heading' => 'Our response', 'paragraphs' => ['Reports will be assessed based on impact, reproducibility, affected users, and exploitability. Marine may request additional safe evidence, coordinate a remediation timeline, and notify affected users or authorities where required. A report does not authorize activity that would otherwise be unlawful.']],
            ['heading' => 'Protect credentials', 'paragraphs' => ['Marine staff will not ask for your password. Never send passwords, MFA secrets, recovery codes, session cookies, signed login links, private keys, or production API credentials in a report.']],
        ],
        'links' => [['Tocsin Data', 'https://tocsindata.com']],
    ],
    'terms' => [
        'title' => 'Terms of Service',
        'intro' => 'These Terms govern access to and use of Marine. By creating an account or using the authenticated service, you agree to the version presented to you at acceptance time.',
        'sections' => [
            ['heading' => '1. The service', 'paragraphs' => ['Marine is a free vessel-record and marine-operations application provided as an organizational and informational aid. Features may be added, changed, suspended, or removed as the service develops. Marine is not sold as an approved navigation, emergency-monitoring, rescue, accounting, insurance, certification, or regulatory-compliance service.']],
            ['heading' => '2. Eligibility and accounts', 'paragraphs' => ['You must provide accurate registration information, maintain control of your email address, protect your credentials, and promptly report suspected unauthorized access. You are responsible for activity performed through your account unless caused by a failure within Marine’s control. You may not create accounts through deception, automation that bypasses controls, or on behalf of another person without authority.']],
            ['heading' => '3. Safe use', 'paragraphs' => ['You remain responsible for vessel operation, voyage planning, navigation, regulatory compliance, equipment, crew, and emergency decisions. Verify safety-sensitive information with current official sources and appropriate onboard equipment. Never delay contacting emergency services because of information displayed—or not displayed—by Marine.']],
            ['heading' => '4. Your content', 'paragraphs' => ['You retain ownership of information and files you submit. You grant Marine the limited permission needed to store, process, back up, display to authorized users, and transmit that content solely to operate and secure the service. You must have the right to upload and share the content and must not use Marine to store unlawful, malicious, infringing, or unauthorized personal information.']],
            ['heading' => '5. Shared access', 'paragraphs' => ['When you invite another user to a vessel, you are responsible for selecting appropriate access and removing it when no longer needed. Shared users must use information only for the authorized vessel purpose. Marine may record invitation, role, access, and revocation events for security and accountability.']],
            ['heading' => '6. Availability and external data', 'paragraphs' => ['The service and external sources may be unavailable, delayed, incomplete, corrected, or discontinued. Marine does not guarantee continuous availability, source coverage, positional accuracy, forecast accuracy, delivery of a notification, or preservation of a record beyond the documented retention and backup process.']],
            ['heading' => '7. Acceptable use', 'paragraphs' => ['You must follow the Acceptable Use Policy. Marine may rate-limit, suspend, or terminate access reasonably believed to threaten users, security, infrastructure, legal compliance, or service integrity. Where practical, users will be given notice and an opportunity to export data, except when immediate action is necessary.']],
            ['heading' => '8. Disclaimers and liability', 'paragraphs' => ['To the extent permitted by applicable law, Marine is provided without warranties that it will be uninterrupted, error-free, complete, current, or fit for a particular voyage or operational decision. Nothing in these Terms excludes rights or liabilities that cannot legally be excluded. Users should maintain independent copies of records that are legally, financially, or operationally essential.']],
            ['heading' => '9. Ending use', 'paragraphs' => ['You may stop using Marine and request account deletion. Marine may end the service or an account for serious or repeated violations, security risk, legal requirement, or operational necessity. Deletion and retention follow the Privacy Notice and Account Data page.']],
            ['heading' => '10. Changes', 'paragraphs' => ['Material changes will be identified by a new version and effective date. Where appropriate, authenticated users will be asked to accept the new version before continuing. Continued use after a valid acceptance means the updated Terms apply from their effective date.']],
        ],
    ],
    'privacy' => [
        'title' => 'Privacy Notice',
        'intro' => 'This Notice explains how Marine collects, uses, discloses, retains, protects, and provides access to personal information. Marine is designed around accountability, limited purposes, consent, access, accuracy, safeguards, and retention control.',
        'sections' => [
            ['heading' => 'Information we collect', 'items' => ['Account information such as name, email, password hash, preferences, policy acceptances, and security events', 'Vessel, equipment, maintenance, task, voyage, budget, log, crew, and emergency-contact information you enter', 'Documents and photos you deliberately upload', 'Location and device information only when tracking or location features are deliberately used', 'Technical information needed for security and operation, such as IP address, session, browser, request time, error and audit data', 'Support communications and privacy requests']],
            ['heading' => 'Why we use it', 'items' => ['Create and secure your account', 'Provide vessel and operational features', 'Apply permissions and sharing choices', 'Deliver requested reminders, alerts, exports, and support', 'Diagnose failures, prevent abuse, investigate security incidents, and maintain audit evidence', 'Comply with applicable law and enforce service terms', 'Improve the service using privacy-minimized information']],
            ['heading' => 'Location information', 'paragraphs' => ['Location tracking is off by default. When enabled, Marine may process coordinates, time, accuracy, course, speed, device, vessel, and track information. The interface must show that tracking is active and provide controls to stop, retain, delete, or share location according to available settings. Location can reveal home, habits, travel, and absence; treat shared links as sensitive.']],
            ['heading' => 'Sharing and service providers', 'paragraphs' => ['Marine discloses information to users you authorize, service providers needed to host, store, email, secure, back up, or support the application, and authorities where disclosure is required or permitted by law. Providers receive only the access needed for their function and should be bound by appropriate confidentiality and security terms. Marine does not sell personal information or use it for third-party behavioural advertising.']],
            ['heading' => 'Retention', 'paragraphs' => ['Information is retained while needed to provide the account and for documented security, audit, dispute, legal, and backup purposes. Tracking and exports should have shorter configurable retention. Deleted information may remain in encrypted backups until normal rotation expires. Audit records may be retained or anonymized where necessary to protect users and service integrity.']],
            ['heading' => 'Safeguards', 'paragraphs' => ['Marine uses role and policy checks, password hashing, secure sessions, rate limiting, private file controls, audit records, dependency checks, backups, and other administrative and technical safeguards appropriate to the information. No online service can promise absolute security; users should protect credentials and report suspicious activity promptly.']],
            ['heading' => 'Your choices and rights', 'paragraphs' => ['You may review and correct account information, manage sharing and notifications, stop tracking, request access or export, and request deletion subject to lawful exceptions. Identity must be verified before disclosing or changing protected information. If a request is refused or limited, Marine should explain the reason where legally permitted.']],
            ['heading' => 'Canadian privacy context', 'paragraphs' => ['Depending on the organization, activity, and location, Canada’s federal private-sector privacy law or substantially similar provincial legislation may apply. British Columbia’s Personal Information Protection Act governs many private-sector organizations in the province, while PIPEDA may apply to interprovincial or other covered commercial activity. This Notice is intended to support those privacy principles without limiting rights provided by applicable law.']],
            ['heading' => 'Questions and complaints', 'paragraphs' => ['Privacy questions, access/correction requests, and complaints should be sent through the authenticated support process or Tocsin Data’s published contact channel. Provide enough information to identify the issue but do not send passwords or unnecessary sensitive records. A privacy complaint will be investigated and documented. You may also contact the privacy regulator with jurisdiction over your concern.']],
        ],
        'links' => [['Office of the Privacy Commissioner of Canada', 'https://www.priv.gc.ca/en/'], ['Office of the Information and Privacy Commissioner for British Columbia', 'https://www.oipc.bc.ca/']],
    ],
    'cookies' => [
        'title' => 'Cookie Notice',
        'intro' => 'Marine uses essential cookies and comparable browser storage to authenticate users, protect requests, remember security-relevant session state, and provide requested preferences.',
        'sections' => [
            ['heading' => 'Essential use', 'paragraphs' => ['Session cookies keep you signed in, associate requests with the correct session, and help defend against forged requests. Preference storage may remember items such as theme or selected vessel. Disabling essential cookies will prevent authenticated features from working correctly.']],
            ['heading' => 'No advertising cookies', 'paragraphs' => ['Marine does not use third-party advertising cookies and does not build profiles for behavioural advertising. Optional analytics must not be enabled without a documented purpose, data-minimization review, appropriate notice or consent, and a way to exercise applicable choices.']],
            ['heading' => 'Managing cookies', 'paragraphs' => ['Browsers allow you to inspect and remove stored data. Removing Marine session cookies will generally sign you out. Shared-device users should sign out and close the browser rather than relying only on automatic expiry.']],
        ],
    ],
    'acceptable-use' => [
        'title' => 'Acceptable Use Policy',
        'intro' => 'Use Marine in a lawful manner that respects other users, service integrity, intellectual property, privacy, and marine safety.',
        'sections' => [
            ['heading' => 'You may not', 'items' => ['Access or attempt to access another person’s account, vessel, files, location, or administrative functions without authorization', 'Probe, scan, exploit, disrupt, overload, or bypass security or rate limits except under an expressly authorized security test', 'Upload malware, unlawful content, stolen data, or material you have no right to store', 'Use location or shared-vessel information to stalk, harass, intimidate, or facilitate wrongdoing', 'Misrepresent Marine information as an official chart, warning, certification, inspection, emergency response, or regulatory approval', 'Automate bulk extraction, scraping, resale, or redistribution contrary to provider terms or service limits', 'Use the service to infringe copyright, privacy, contractual, or other rights', 'Impersonate a person, vessel owner, authority, or service operator']],
            ['heading' => 'Enforcement', 'paragraphs' => ['Marine may limit, suspend, quarantine, or terminate activity that creates a credible security, legal, safety, privacy, or operational risk. The response should be proportionate and documented. Immediate action may occur without advance notice when needed to protect users or systems.']],
            ['heading' => 'Reporting misuse', 'paragraphs' => ['Report suspected abuse through support without confronting another user or collecting additional private data. Preserve relevant non-sensitive details such as time, page, and record identifier. Emergency or criminal activity requiring immediate action should be reported to the appropriate authority.']],
        ],
    ],
    'location-notice' => [
        'title' => 'Location Data and Tracking Notice',
        'intro' => 'Precise location is sensitive. Marine does not enable tracking merely because a user registers or adds a vessel.',
        'sections' => [
            ['heading' => 'What tracking may collect', 'paragraphs' => ['When deliberately enabled, tracking may collect latitude, longitude, time, reported accuracy, course, speed, altitude, device identifier, vessel association, and technical quality information. Browser and device readings can be delayed, unavailable, or inaccurate.']],
            ['heading' => 'Consent and control', 'items' => ['Tracking must be enabled through an explicit action.', 'The interface must show when tracking is active.', 'You can stop and revoke a device.', 'Retention and sampling controls apply where offered.', 'Tracks remain private unless deliberately shared.', 'A shared link must be time-limited and revocable.']],
            ['heading' => 'Important risks', 'paragraphs' => ['Location history may reveal a home address, marina, routines, valuables, travel plans, or when people are away. Share only with people who need it. Do not publish float-plan or tracking links openly. A location shown by Marine is not guaranteed to be current and must not be treated as an emergency beacon or continuous watch.']],
            ['heading' => 'Retention and deletion', 'paragraphs' => ['Marine should retain precise points only for the period selected or operationally required, subject to documented security and backup limits. Stopping tracking does not automatically delete earlier tracks; use the available deletion or retention controls. Revoking a share link stops future access through that link but cannot make a recipient forget information already viewed.']],
        ],
    ],
    'uploaded-content' => [
        'title' => 'Uploaded Content and Document Policy',
        'intro' => 'Marine document storage is intended for vessel and operational records that you are authorized to keep, such as registration, insurance, surveys, manuals, warranties, receipts, licences, and qualifications.',
        'sections' => [
            ['heading' => 'Your responsibilities', 'items' => ['Upload only material you own or are authorized to store.', 'Do not upload unnecessary identity documents, payment-card details, health records, passwords, private keys, or credentials.', 'Classify and share documents carefully.', 'Keep independent copies of legally or operationally essential records.', 'Remove records that are no longer needed, subject to applicable retention obligations.']],
            ['heading' => 'Service controls', 'paragraphs' => ['Marine may restrict file type and size, verify content type, rename stored files, calculate checksums, scan for malware, quarantine suspicious content, and refuse unsafe uploads. Authorization is checked when a private file is viewed or downloaded. A filename or link alone must not grant access.']],
            ['heading' => 'Removal', 'paragraphs' => ['Material may be removed or quarantined when it creates a security risk, violates this policy, infringes rights, or must be removed by law. Where practical and safe, the account owner will be notified. Deletion from active storage does not imply immediate deletion from every backup copy.']],
        ],
    ],
    'account-data' => [
        'title' => 'Account Deletion and Data Export',
        'intro' => 'Marine users should be able to obtain a useful copy of their information and end use of the service without hidden retention claims.',
        'sections' => [
            ['heading' => 'Export', 'paragraphs' => ['An authenticated export should include account settings, owned vessel records, tasks, maintenance, expenses, logs, voyages, positions subject to retention, sharing relationships, and uploaded files in documented machine-readable and original-file formats. Export archives are sensitive, expire after a limited period, and require secure access.']],
            ['heading' => 'Before deletion', 'paragraphs' => ['Vessel owners may need to transfer ownership, notify shared users, download records, or cancel active float-plan links. Marine must verify the request and explain the effects before deletion begins. A short cancellation period may be used if clearly disclosed.']],
            ['heading' => 'What deletion does', 'paragraphs' => ['Deletion removes or anonymizes active account data according to ownership, sharing, security, legal, and audit requirements. Shared contributions may need to remain with the vessel under another owner while the deleted user’s identity is minimized. Security records may be retained where necessary to prevent abuse or document material actions.']],
            ['heading' => 'Backups', 'paragraphs' => ['Encrypted backup copies are not rewritten instantly for each deletion request. Deleted information may remain inaccessible in backups until those backups expire through rotation. Restored backups must reapply completed deletion records before normal operation.']],
        ],
    ],
    'copyright' => [
        'title' => 'Copyright and Intellectual Property',
        'intro' => 'Marine application code, original design, and original written content are protected unless a specific licence says otherwise. Third-party products and data retain their own ownership and terms.',
        'sections' => [
            ['heading' => 'Your information', 'paragraphs' => ['You retain rights in content you lawfully submit. The Terms grant Marine only the limited permission needed to operate, secure, back up, and present it to authorized users.']],
            ['heading' => 'Third-party data', 'paragraphs' => ['Maps, charts, forecasts, notices, imagery, icons, software, and datasets may require attribution or restrict copying, caching, modification, or redistribution. Access through Marine does not grant a user broader rights than the original provider permits.']],
            ['heading' => 'Canadian Hydrographic Service material', 'paragraphs' => ['CHS products and data may be protected by Crown copyright and require licensing. Marine will identify approved chart products and attribution separately; a general data link is not permission to reproduce a chart.']],
            ['heading' => 'Reporting concerns', 'paragraphs' => ['A rights holder may report allegedly infringing material through support with identification of the work, location of the material, authority to act, and contact information. Marine may restrict access while assessing a credible report.']],
        ],
        'links' => [['CHS copyright and licensing', 'https://www.charts.gc.ca/copyright-droitdauteur/index-eng.html']],
    ],
    'open-source' => [
        'title' => 'Open-Source Software Notices',
        'intro' => 'Marine is built with Laravel and other open-source packages. Those components remain governed by their respective licences and attribution requirements.',
        'sections' => [
            ['heading' => 'Application dependencies', 'paragraphs' => ['The authoritative dependency inventory is generated from committed lockfiles and the deployed release. Because packages change over time, this page should be accompanied by a release-specific licence report rather than a static claim that can become outdated.']],
            ['heading' => 'No change to third-party licences', 'paragraphs' => ['Marine’s Terms do not replace or restrict rights granted directly by an open-source licence. Trademarks, provider names, datasets, fonts, icons, and other assets may have separate terms even when the software package itself is open source.']],
            ['heading' => 'Source and notices', 'paragraphs' => ['Where a licence requires source, notices, attribution, or a copy of its text, release packaging and documentation must provide it. Suspected omissions can be reported through support.']],
        ],
        'links' => [['Laravel', 'https://laravel.com/'], ['Laravel licence', 'https://github.com/laravel/framework/blob/12.x/LICENSE.md']],
    ],
    'emergency' => [
        'title' => 'Emergency Information and Limitations',
        'intro' => 'Marine is not continuously monitored, does not receive distress calls, does not dispatch rescue resources, and cannot guarantee delivery of an alert, message, float plan, or position.',
        'sections' => [
            ['heading' => 'If there is immediate danger', 'paragraphs' => ['Use the official emergency and marine communications appropriate to your location and equipment. In Canadian waters, Canadian Coast Guard guidance identifies VHF channel 16 (156.8 MHz) for marine distress and safety communications; other official frequencies and contact methods may apply. Use current official guidance and trained radio procedure.']],
            ['heading' => 'Do not wait for the application', 'paragraphs' => ['A missing warning, unchanged position, overdue float plan, failed notification, or unavailable source does not prove that a vessel is safe. Contact the appropriate authority directly when circumstances warrant. Do not assume support staff are watching the dashboard.']],
            ['heading' => 'Prepare independently', 'items' => ['Carry required safety and communications equipment.', 'Keep emergency contacts and vessel information available offline.', 'Use an appropriate float plan and a responsible contact.', 'Monitor official forecasts, warnings, notices, and radio broadcasts.', 'Maintain navigation equipment, charts, publications, and backup methods.', 'Know the limitations of cellular, satellite, browser, GPS, and battery-dependent systems.']],
        ],
        'links' => [['Canadian Coast Guard MCTS emergency contact guidance', 'https://www.canada.ca/en/canadian-coast-guard/corporate/contact-us/contact-marine-communications-traffic-services.html'], ['Canadian Coast Guard', 'https://www.canada.ca/en/canadian-coast-guard.html']],
    ],
    'changelog' => [
        'title' => 'What’s New',
        'intro' => 'This page records user-visible changes. Dates describe when work entered the application branch or release process; they do not imply that every development feature is deployed to production.',
        'sections' => [
            ['heading' => '2026-08-19 — Foundation milestone', 'items' => ['Standardized the application on Laravel 12.', 'Added free registration, login, logout, email verification, rate limits, and policy acknowledgement.', 'Added owner-scoped vessel create, view, edit, and archive workflows.', 'Added general and vessel-specific tasks.', 'Added audit-event storage and a terminal-only super-administrator command.', 'Added the first responsive dashboard and full public information structure.', 'Kept external marine providers disabled until source and licensing approval.']],
            ['heading' => 'How releases are described', 'paragraphs' => ['Future entries should separate added, changed, fixed, security, deprecated, and removed behavior. Security details may be delayed until affected users are protected. Provider activation must identify its source and operational approval state.']],
        ],
    ],
];

$item = $content[$page];
@endphp

@extends('layouts.app', ['title' => $item['title']])

@section('content')
<article class="prose">
    <p class="eyebrow">Version 1.0 · Effective 2026-08-19 · Last reviewed 2026-08-19</p>
    <h1>{{ $item['title'] }}</h1>
    <p class="lead">{{ $item['intro'] }}</p>

    @foreach ($item['sections'] as $section)
        <section>
            <h2>{{ $section['heading'] }}</h2>
            @foreach ($section['paragraphs'] ?? [] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            @if (! empty($section['items']))
                <ul>
                    @foreach ($section['items'] as $listItem)
                        <li>{{ $listItem }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach

    @if (! empty($item['links']))
        <section>
            <h2>Authoritative references</h2>
            <ul>
                @foreach ($item['links'] as [$label, $url])
                    <li><a href="{{ $url }}" rel="noopener noreferrer">{{ $label }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</article>
@endsection
