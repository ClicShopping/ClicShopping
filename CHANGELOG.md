version4.29
- Maintenance version
- improvement
- fix / bug resolution
- libraries updated

---------------
version4.281 et 4.282
fix upload
---------------
version4.28
- php9 compatibility improvement
- libraries updated
- AI fix and improvement
- AI SEO refactoring, faq introduced and improvement analysis for the front office via different analysis
- AI Orchestrator refactored and include some improvement
- AI WebsSearch refactored with new improvement (competitive intelligence introduced using SERPAI : Analysis trend and market, google shopping and Amazon)
- Security fix

---------------
version4.27
- MCP : Anthropic Ecommerce Connector
- libraries updated
- Some bug fix and improvement
- Agents.md updated

---------------
version4.26
- remove table embedding no compatible with sql server inside schema
- Add AI product recommendations (refactoring) inside product description (to do the cart, checkout  ... later)
- librairies updated

---------------
version4.25
- librairies updated
- Some fix and security improvement
- Add papers company checking for customers B2b
- Add electronic EU invoice regulation
- AI Sentiment Analysis improved
- AI Customer recommendation refactored

---------------
version4.24
- Some fix
- add shop cron for product Cockpit Ai
- fix constant bug in CokpitAI

---------------
version4.23
- Some fix
- fix bug dynamicPrice on specials 
- Libraries updated
- update AGENTS.md to help the AI agents
- New AI Product advanced cockpit analysis with forecasting metrics (around 30 kpi), and autonomous action to do with realtime product AI tracking.
- Small AI SEO improvement
- LLM model updated for OpenAI and add Gemini
- Add google Analytics v4 GA
- add claude.md and use agenta.md
- products attributes refactoring

---------------
version4.22
- AI
    - SerpApi refactored and using new AI agentic approach with Google AI Overview Results API
    - Add new check SEO for categories and products based on AI agentic approach with SerpApi
    - AI interface improvement
    - MCP improvement
- Some fix
- Libraries updated
- add AGENTS.md to help the AI agents


---------------
version4.21
- AI
   - AI improvement and refactoring to be full agentic approach
   - Agent - Actor - Critic - Objectives - Evaluation - Conversation included
   - Around 50 KPI for statistics (manager, programmer, data scientist)
   - Domain Tools implementation with stock forecast example.
   - App Ecommerce Domain with Insight (basic) approach including ACP et UCP below
   - Refactoring ACP and add UCP Beta for AI Ecommerce App
     - ACP Protocol Beta refactored via Ecommerce AI app
     - UCP Protocol Beta added via Ecommerce AI app
   - SEO improvement
   - MCP updated
- Some fix (included deprecated)
- Libraries updated
- php9 compatibility

---------------
version4.20
AI
   - OM Interfaces and Abstract refactored
   - Multi-Domain Agnostic AI Framework 
   - First php 9 compatibility   
   - some fix (included deprecated)
   - Librairies updated
   - Dynamic Pricing improved

---------------
version4.12
  - Libraries updated
  - small fix 
  - IA improvement :
      - Add comment to sql schema
      - Prompts refactoring
      - LLmprovider refactoring
      - Add parallel execution for multiple tasks
      - Parallel analysis optimized inside agent
      - Cache optimized et intelligent timeout management
      - AI directory structure refactored
---------------
version4.11
- Libraries updated
- small fix
- Include LmStudio, Ollama, private llm updated
- New RAG BI (Monolitic to Agentic) refactoring allowing Agentiec & reasonning & planification approach
   -  **LmStudio, Ollama, private llm** framework
   -  **Architecture Agentique** (Multi-Agent System)
   -  **Reasoning** (Chain-of-Thought, Tree-of-Thought & Self Consitency)
   -  **Planning** (Task decomposition, Sub-task planning)
   -  **Hybridation** (Analytics + Semantic + Web Search)
   -  **Multi-LLM** (5+ providers, dynamic selection)
   -  **Memory System** (Working + Conversation + Entity tracking)
   -  **Self-Correction** (Error detection, SQL fixing, retry)
   -  **Tool Execution** (Calculator, Web Search, Custom tools)
   -  **Security** Check what's happen inside the prompt 
   -  **No micro service**
   -  **Simple installation one click**
   - Mcp server connector improvement
   - remove data simulation
   - add multi server capability


----------------
version4.10
- Libraries updated
- small fix
- App refactoring to be more easy to use, create or to install a new App
- Mcp security increased
- New admin design

----------------
version4.09
fix db error
fix install for maria db v11.8 (driver)
Libraries updated
-----------------
version4.08
Refactoring
small fix
Libraries updated
MCP (model context Protocol) for e-commerce agentic approach
MCP Wiki on github.com/ClicShopping

-----------------

version4.07
add files missing in 4.06
-----------------
version4.06
Dynamic Price action in function of the stock and sales introduction
Libraries updated
Refactoring
fix small bugs
security improvement
Add Redis cache as option
cache improvement
* - Exception handling
* - Namespace support to avoid collisions
* - Data compression
* - Cache metadata
* - Batch operation methods
* - Fluent interface
* - Memory management
little design modification

-----------------
version4.05
Libraries updated
Refactoring
fix small bugs
Event / event source / stream system improved
url rewriting secure improvement
Add gpt-5 / and Gpt-Goss model
AI audit improvement

-----------------
version4.04
Libraries updated
Limit the analytics context sent to the AI
Refactoring
fix small bugs

------------------
version4.03
Libraries updated
includes Guardrails classes to evaluate the prompt analytics result

------------------
Version4.02
librairies updated
Improve security content html editor 
Rename Includes Directory by Core
small improvement and fix
Rename includes directory into Core

------------------
version4.011
Increase the api security with some checks
librairies updated
Documentation improved (wiki)

------------------
version4.01
remove schema forgotten

-----------------
version4.00

The version 4.x integrate the embeddings and need to be used with mariaDB 11.7 at least if you want to use it. An database update is available in GTP Apps.

note : This update works only with mariaDb and not mySQL 9.x due a difference in the vectorization.

The goal is to exploit the Gen AI capacities with a hybrid rag approach creating a RAG BI Agent.

- small bug fix
- librairies updated
- Protect data information by AES-256
- New double authentification approach by email
- Add product recommendation module for products_info template using the embedding
- Add embedding for some tablea and some files updated to use it.
- Add rag via chatbot for administration
- Rag with agent BI
 - -  Allow to use the rag and retrieve the data inside the database
 - -  Allow the rag to search as specific information via a text request
 - -  Allow the rag to make statistic via dynamic query
 - -  Intelligent rag with agent BI allowing to use different embedding model
- API improvement and inclusion OpenAi GPT Cart
- OpCache included
- Memcached included
- Static cached template included
- Security improvement
- Code refactored and optimized
- Add a new parameter for Hooks to allow display a distinct hook for each tab

------------------
version3.457
Fix bug
DB improvement
Librairies updated

----------------
version3.456
Librairies updated
fix categories gpt call js
fix error date (customer admin creation)
add more language information inside the prompt
Add AI LLM model
fix small error
Add new Ollama model

------------------

version3.455
documentation improved
Librairies updated
php8.4 improvment
allow to use llm vectorisation

------------------

version3.454
documentation improved
fix bug
Librairies updated

------------------
version3.453
PHP 8.4 : fix deprecated element

------------------
version3.452
Librairies updated

------------------
version3.452
Librairies updated
fix template wisywig email
small fix
Had encryption / decryption string

--------------------
version 3.451
Librairies updated
Remove ckeditor4
Improve modal for chatgpt communication
Minor bugfix
fix copy product

--------------------
version 3.450
Librairies updated
Add stats gpt usage removed in  3.449
gpt app and module refactoring
code refactoring for better compatibility
add fields mandatory for products attributes
Improve SEO GPT
Replace Gemma by Gemma2 for ollama
fix error products_attributes and check field
Add Anthropic Sonnet3.5,Opus, Haiku


--------------------
version 3.449
Librairies updated
Add llphant librairies
Allow to use some different modal on the server (need a dedicated server and ollama installled)
Small bugs
Add Ollama and Gpt inside one class
Remove Dall-E

--------------------
version 3.448
bootstrap 5.3.3
Librairies updated
small bug fix
bootstrap-table updated
add search in some forms


--------------------
version 3.447
elfinder updated
bootstrap-table updated
Add bootstrap-table for mobile
Librairies updated
fix Undefined array key products attributes
improve securities for constant
Improve table for mobile
update doc
Minimum php version requirement >8.2
fix gpt save button
fix link admin to go a new page

--------------------
version 3.445
fix db errors
fix install configuration

--------------------
version 3.444
Librairies updated
Add new security access to configure.php for Admin
Small bugs



--------------------
version 3.443
Add status for administror menu
fix some small bugs
Librairies updated
add status for administrators

--------------------
version 3.442
fix error sql db
fix error acces addmin
Add status for administrormenu
fix somesmall bugs
Librairies updated

--------------------
version 3.441
check ip for admin login
fix link orders dashboard
Update ckeditor
Stripe

--------------------
version 3.440
Remove Icon not used
fix small bug 
refactoring
fix delete language ofr reviews

--------------------
version 3.439
Vote evaluation statistics
jquery-3.7.1.min.js
bug fix
refactoring
stats report customer vote
Refactoring install Apps allowing to use some other Db (preparation) 
Database reorganisation

--------------------
version 3.438
Remove Davinci for Gpt3.5-turbo
Url Rewiting improvement
Recommendations refactored

--------------------
version 3.437
sql update

--------------------
version 3.436
Favorites automation based on recommendations (creation)
Featured automation based on recommendations (creation)
Review sentiment analysis AI
fix who's online dashboard
libraries updated
bootstrap 5.3.2 and icons
fix javascript for chrome/edge/opera checkout confirmation button

--------------------
version 3.435
Allow to the admin to change the products recommend status
Add check for chatbot front office
libraries updated
Refactoring

--------------------
version 3.434
Brace function updated
Fix db syntax for marketplace

--------------------
version 3.433 and 3.4331
fix version confusion

--------------------
version 3.432
fix db import markeplace_file_information

--------------------
version 3.431
Preditive security stock report
small fix
Librairies updated
generative AI product recommendations updated
generative AI product search info chatbot for frontpage
genrative AI reviews tag
Gpt stats improved
Stripe V12$

--------------------
version 3.430
fix date time action recorer
Librairies updated

--------------------
3.429
Add new stats for reviews
small fix
Librairies updated
product recommendations added
fix access error documents directory

-------------------
3.428
Suppliers improvement (Stats)
who s online improvement
Gpt refactoring
New stats and dashboard graph for Gpt
Add ckeditor 5 as admin option
small fix
Librairies updated

-------------------
3.427
Manufacturers improvement
sql fix marketplace db

-------------------
3.426
Little refactoring
librairies updated
small fix
productCommon Class refactoring
Currency, Does not allow to delete default status
Product unit qty little improvement
fix bug display discount
Manufacturers improvement
Add shipping Delay out of stock
Add products recommendations class
Librairies Elfinder updated

-------------------
3.425
Little refactoring
librairies updated
small fix
bootstrap 5.3
update config_clicshopping boolean change
fix error_documents bug

-------------------
3.424
add missing products_no_purchased files
fix order map chart dashboard
fix backup cron error
fix js Chapgpt when is not used
Banner delete improvement
add missing products_no_purchased files
fix order map chart dashboard

-------------------
3.423
add image forgotten

-------------------
3.422
small fix
librairies updated
copy confirm improvment
fix category tree
fix url page manager
-------------------
3.421
fix class call 
update javascript version
fix bug select categories Dropdown (product)

-------------------
3.420
Add stats report product no purchased
antispam refactoring
small fix
Email security login connexion catalog side
add constant debug mode to use for development

--------------------
3.419
fix js error
OpenIA GPt add organization and gpt 4 options
libraries updated

--------------------
3.418
Add OpenAI Gpt-4

--------------------
3.417
Improve Chatgpt
Fix manufacturer bug

--------------------
3.416

fix manufacturer
update librairies

--------------------
3.415
fix fucntion error return getManufacturerName

--------------------

3.414
Gppt improved
Update librairies
Small fix
Submit description BD renamed in seo

--------------------
3.413
remove api chatgpt obsolete in french db
little improvement
small bug fix
template html updated
fix api bug

--------------------
3.411 - 3.413
Db fix

--------------------
3.410

Add ChatGpt
Libraries updated
Small fix
refactoring codemirror call

--------------------

3.409
fix sql installation

--------------------

3.408
Fix bug
Librairies updated
Stripe 10.5
New marketplace
antispam improvment


--------------------

3.407
New Apps Antispam
Librairies updated
Little bug fix
Remove star font awesome by bootstrap icon 
Add GDPR Stat top Dashboard
Add new Hook Stats
Fix gdpr time
Order text improvment
Add new Hooks ShoppingCart
add accordeon for description language field (products, categories, page manager)

--------------------
3.406       
fix php8.2 compatibility
strtolower => mb_strtolower
mb_strtoupper => mb_mb_strtoupper
utf8_decode => CLICSHOPPING::utf8Decode()
utf8_encode => CLICSHOPPING::utf8Encode()
others little fix
Add API REST Apps
Refactorisation

--------------------
3.405
Bootstrap 5.2.3
fix url error due xss patch when the rewriting is off
--------------------
3.404
fix sql install violation
Help to generate password

--------------------
3.403
fix deprecated js condiftion elseif
add http link for logo invoice order in admin
add typage for function
fix XSS-Reflected

--------------------
3.402
Add new class tva intracom Check
Librairies updated
fix js error elseif deprecated

--------------------
3.401
nothing
--------------------
3.400
fix db error installation
fix js error admin

--------------------
3.339
fix sql missing 
fix js error

--------------------
3.339
fix and add pop up install db
fix strotime deprecated
add double authentification Topt approach
some fix

--------------------
3.338
fix db sql installation

--------------------
3.337
fix db demo installation

--------------------
3.336
Add new fields in product
Update cron app
update librairies
add link to install app in upgrade
bug fix suppliers
--------------------
3.335
fix error document
Add new hooks
Add product return management
bootstrap 5.2
Gdpr Optimization
fix bug stats product notification
add cronjob


see sql update for modification
You must activate the rma module and cron module

--------------------
3.334
add phpmailer error
update error_documents directory
add ip restriction management
fix upgrade check and app redesign
add gdpr inside customer table
add gdpr management and header tag
add design studio tool
new design install process
see sql update to update your shop

--------------------
3.333
Allow to edit address in the order
update smtp debug
fox html div in order

--------------------
3.332
Add new function to select template
Add new DB (see sql update)
Allow to select a banner in function the theme
Little fix
Email refactoring


----------------------
3.331
fix Wysiwsyg call
fix insert image
update composer libraries
Update sql countries

----------------------
3.329
fix File download attributes never deleted
More flexibility to update and change wysiwyg
fix little bug

----------------------
3.328
fix bug email
add help information for customer company

----------------------
V.327
Update librairies bootstrap table
fix stripe
replace icon by svg
libraries update

----------------------
3.326
Add Sitemap inside Seo page
Add a specific robot.txt
add new version bootstrap icon
fix seo cart link

----------------------
3.325
Fix boxes selected in function the template file selected

----------------------
3.324
Fix code error identified in ClicShopping

----------------------
3.323
fix syntax error

-------------
3.322
revert ckeditor 4 due to impact ckeditor 5
fix github proprieties access
upgrade rewriting via htaccess
fix email impact on session shoppingcart 
fix small bugs

-------------
3.321
update bootstrap@5.2.0
update librairies

-------------
3.320
fix order pdf website info link
add a check on page manager
update invoice number for pdf
update bootstrap icon 1.8.3
update bootstrap@5.2.0-beta1
small other fix
Orders : Add a simple order archive batch on 30d,
fix possible warning DOMDocument::loadHTML(): Tag figure invalid in Entity with ckeditor
Move security check inside Modules
Move securityPro inside Modules
add ckeditor 5.x

-------------
Version 3.319
Update github due a commit problem
libraries updated

-------------
Version 3.318
fix Admin no image
libraries update
add a new Title SEO Index page (cf request sql for update)
add id on buy button

-------------
Version 3.317
fix db install
remove info application size

-------------
Version 3.316
Automatic install detection for the first installation
code refactoring
add support menu with wiki link (technical doc)
error template updated
librairies updated.

-------------
Version 3.315
Ckeditor version updated
Librairies updated
fix Mail classes
Code refactoring

-------------
Version 3.314
fix mail function (deprecated)
move error_document in root
remove constant french installation (can create a deprecated warning)

-------------
Version 3.313
fix error sql

-------------
Version 3.312

code improvement
fix customer group
fix db demo error

-------------
Version 3.311
add sql customer groups (see sql upgrade)

------------
Version 3.310
Spam Fight improvment
Librairies update
small bug fix

-------------
version 3.309
fix weight classe not appear inside product shipping tab
librairies update

-------------
version 3.308
some little bugs fixed
librairies update
little fix javascript
add a new shipping tab inside product

-------------
version 3.307
    some little bugs fixed
    librairies update
    php 8.1 compatibility
    Add brand report
    Sql Update see update for instructions

-------------
version 3.306
some little bugs fixed
librairies update
php 8.1 compatibility
add categories_seo_url to customize the url for rewriting
add products_seo_url to customize the url for rewriting

-------------
version 3.305
Minor release,
little fix, update libraries

-------------
version 3.304
Some little fix discover, minor release

-------------
Version 3.03
update B2b

-------------
Version 3.302
 
fix little bug
libraries updated
spam management more strict
php 8.1 compatible
improve speed core
boostrap, elfinder, wysiwyg updated
no Sql update


-------------
Version 3.300 

Note : This version works only with php > 8.0

- fix strict mode php 8
- fix incompatibility with php 8  (php 7.4.x do not work now)
- Some different bug fix
- Code refactoring  
- Boostrap 5 implementation 
- Add vertical menu in option
- remove htaccess (see tip and trips on the forum)
- Replace font Awesome library by bootstrap icon  
- remove customer fax obsolete 
- update sql database (see sql update and update version)
- remove sensiolabs/security-checker not maintained
- add a security checker email library via composer
- reduce jquery dependency by pure javascript
- add new image gallery in pure js (the other will be push on github)
- add chartjs without jquery https://www.chartjs.org/

---
Version 3.2204

Fix rss files request sql errors
fix taxes
fix qty shopping cart
---
Version 3.2201

See SQL upgrade directory to update your database from v3.2145

- upgrade bootstrap 4.5.2
- update composer libraries
- new ckeditor version
- new elfinder version
- remove shop rootdirectory
- fix bug on typo Login be LogIn
- fix bug remove products on reviews
- fix and more strict compatibilities and php > 7.4.3
- fix some little bugs : products stock, dimension
- fix email function on string management
- some language translation fix
- Class and function refactoring to minimum php 7.4.3
- add full link for image
- add stripe payment
- Remove Paypal (push on github)
- fix  some offset error
- add wiki information on Github
- update SEO
- add new seo system based on page and header tag
- add sitemap header tag
- Code refactoring
- and more
