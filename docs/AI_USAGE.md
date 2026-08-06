# AI_USAGE.md

# AI Usage

## Overview

ClicShopping AI provides AI-assisted features to help merchants perform content creation and administrative tasks within the ClicShopping platform.

ClicShopping AI does not include or train an artificial intelligence model. It acts as an interface between the application and one or more AI providers configured by the administrator.

The availability of AI features depends on the installed applications and system configuration.

---

# AI-Assisted Features

Depending on the installed modules, AI can be used for tasks including:

* Generate product descriptions
* Improve existing product content
* Generate SEO titles and meta descriptions
* Generate Frequently Asked Questions (FAQ)
* Generate product summaries
* Create or enhance product attributes
* Assist with product creation
* Generate administrative reports
* Generate marketing content
* Assist with translations
* Other AI-assisted content generation features provided by installed applications

All generated content should be reviewed before publication.

---

# Supported AI Providers

ClicShopping AI supports multiple AI providers.

Without limitation, supported providers include:

* OpenAI
* Anthropic
* Google Gemini
* Mistral AI
* Ollama (local deployment)

Additional providers may be supported through extensions or future integrations.

The administrator selects the AI provider according to the organization's requirements.

---

# Data Sent to the AI Provider

Depending on the requested operation, ClicShopping AI may transmit information such as:

* Product titles
* Product descriptions
* Product specifications
* Category names
* Keywords
* SEO metadata
* User prompts
* Selected language
* Context required to generate the requested content

Only the information necessary to perform the requested AI operation should be transmitted.

The exact data transmitted depends on the feature being used.

---

# Data Not Sent

Unless explicitly required by an installed application or custom development, ClicShopping AI does not intentionally transmit:

* Customer passwords
* Administrator passwords
* Payment information
* Credit card data
* Authentication tokens
* Private encryption keys
* Database credentials
* Server configuration files
* Source code of ClicShopping
* Complete database contents

Administrators remain responsible for verifying the behavior of custom modules and third-party extensions.

---

# AI Response Processing

Responses returned by the AI provider are processed by ClicShopping AI and displayed to the user.

Generated content can generally be:

* edited;
* accepted;
* rejected;
* regenerated;

before being saved or published.

---

# System Limitations

Artificial intelligence may generate content that is:

* inaccurate;
* incomplete;
* outdated;
* inconsistent;
* misleading;
* inappropriate for a specific commercial context.

AI-generated content should not be considered authoritative without human verification.

ClicShopping AI does not guarantee:

* factual accuracy;
* legal compliance;
* regulatory compliance;
* SEO performance;
* originality;
* commercial suitability.

---

# Privacy

The privacy policy applicable to AI requests depends on the selected AI provider.

When using external providers, submitted data is processed according to that provider's terms of service and privacy policy.

Administrators should ensure that the selected provider complies with the organization's legal and privacy requirements.

---

# Administrator Responsibilities

The administrator is responsible for:

* selecting an appropriate AI provider;
* configuring API credentials securely;
* defining internal usage policies;
* informing users when required by applicable regulations;
* ensuring compliance with applicable data protection laws.

---

# User Responsibilities

Users are responsible for:

* reviewing generated content;
* validating factual information;
* correcting errors;
* verifying legal compliance before publication;
* ensuring that confidential information is not unnecessarily submitted to an AI provider.

---

# Disclaimer

ClicShopping AI is an AI-assisted productivity tool.

It does not replace professional judgment or human review.

All decisions based on AI-generated content remain the responsibility of the user.
