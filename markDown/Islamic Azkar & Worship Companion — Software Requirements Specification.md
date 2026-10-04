Software Requirements Specification (SRS)

Islamic Azkar & Worship Companion

Document Version: 1.0

Document Status: Draft for Technical Review

Target Platforms: Android, iOS, Web Admin Dashboard

Backend: Laravel

Database: MySQL

Primary Language: Arabic

MVP Scope: Azkar, Duas, Personal Wird, Prayer Times, Qibla, Accounts, Ads, Admin Dashboard

1. Introduction

1.1 Document Purpose

This Software Requirements Specification defines the functional and non-functional requirements for an

Islamic Azkar and worship companion application.

The system consists of:

1.

A mobile application for Android and iOS.

2.

A web-based administrative dashboard.

3.

A Laravel backend.

4.

A MySQL database.

5.

Integration with selected external services where required.

This document establishes a shared understanding between the product owner, designers, developers,

testers, and other stakeholders regarding what the system should provide and how it should behave.

The SRS will serve as the primary reference for:

•

UX and Information Architecture.

•

UI design.

•

Technical architecture.

•

Database design.

•

API design.

•

Development planning.

•

Testing and QA.

•

Acceptance testing.

•

Future product iterations.

1

2. Product Background

2.1 Product Idea

The product is an Islamic mobile application designed to provide users with a simple and organized way

to:

•

Access Islamic Azkar and Duas.

•

Complete repeated Azkar using an in-app counter.

•

Create and manage personal Wird.

•

View daily prayer times.

•

Identify the upcoming prayer.

•

Determine the Qibla direction.

•

Optionally create an account for personalized tracking.

The application is intended to combine several commonly used worship-related utilities into a single

simple experience.

3. Problem Statement

Users may face several problems when maintaining their daily Azkar and Wird:

•

Azkar may be distributed across different sources.

•

Users may find it difficult to track how many repetitions they have completed.

•

Users may lose track of their progress during repeated recitation.

•

Users may want to maintain their own personal Wird in addition to predefined Azkar.

•

Users may need prayer times and Qibla direction while using the same application.

•

Existing applications may provide many features but may not provide the desired cohesive and

simple experience.

The product aims to reduce this friction by providing a focused experience centered around Azkar,

personal Wird, prayer times, and Qibla.

4. Product Vision

The product aims to provide a simple, accessible, and reliable Islamic worship companion where users

can easily access verified content, track repetitions, maintain personal Wird, view prayer information,

and determine Qibla direction without unnecessary complexity.

5. Product Goals & Objectives

5.1 Primary Goals

•

Provide an organized collection of Azkar and Duas.

2

•

Make repetition tracking simple and reliable.

•

Allow users to create their own Personal Wird.

•

Provide daily prayer times based on the user's location.

•

Provide an offline Qibla experience.

•

Allow core functionality without requiring account registration.

•

Provide account-based personalization and future tracking capabilities.

•

Provide administrators with tools to manage Islamic content.

•

Allow content to be updated without requiring a mobile application release.

5.2 Success Indicators

Potential product success indicators include:

•

Number of application downloads.

•

Number of active users.

•

Number of users creating Personal Wird.

•

Frequency of application usage.

•

Usage of Azkar and Personal Wird features.

•

Number of registered users.

•

Content engagement.

Exact analytics definitions and measurement methodology will be established during technical planning

and implementation.

6. Target Users

6.1 Regular Azkar Users

Users who regularly read predefined Islamic Azkar and want an easier way to navigate and count

repetitions.

6.2 Personal Wird Users

Users who maintain their own daily Wird and want to define custom text and repetition targets.

6.3 Prayer-Time Users

Users who want quick access to daily prayer times and the upcoming prayer.

6.4 Qibla Users

Users who need to determine the Qibla direction using their mobile device.

3

6.5 Accessibility-Oriented Users

Users who may benefit from:

•

Adjustable text sizes.

•

High visual contrast.

•

Dark mode.

•

Large touch targets.

•

Simple navigation.

6.6 Dashboard Administrators

Authorized users responsible for:

•

Managing Islamic content.

•

Managing categories and subcategories.

•

Managing administrators and permissions.

•

Reviewing users.

•
•

Viewing analytics.
Monitoring relevant system/service information.

7. Product Scope

7.1 Mobile Application

The mobile application includes:

•

Azkar and Duas.

•

Personal Wird.

•

Prayer Times.

•

Upcoming Prayer.

•

Qibla.

•

User accounts.

•

Guest access.

•

Authentication.

•

Ads.

•

Offline support for supported content.

•

Accessibility features.

7.2 Administrative Dashboard

The web dashboard includes:

•

User overview.

•

Category management.

•

Subcategory management.

•

Dhikr/Dua content management.

•

Audio management.

4

•

Roles and permissions.

•

Analytics.

•

External service information where applicable.

7.3 Backend

The backend provides:

•

API communication.

•

Authentication.

•

User management.

•

Content management.

•

Personal Wird management.

•

Tracking infrastructure.

•

Roles and permissions.

•

Analytics data.

•

Integration with external services where applicable.

8. System Components

The overall system consists of:

                    ┌─────────────────────┐

                    │   Mobile App        │

                    │   Android / iOS     │

                    └──────────┬──────────┘

                               │

                               │ REST APIs

                               │

                    ┌──────────▼──────────┐

                    │   Laravel Backend   │

                    └──────────┬──────────┘

                               │

                    ┌──────────▼──────────┐

                    │      MySQL          │

                    └─────────────────────┘

                               ▲

                               │

                    ┌──────────┴──────────┐

                    │                     │

          ┌─────────┴─────────┐ ┌────────┴─────────┐

          │ Admin Dashboard   │ │ External APIs    │

          │ Web Application   │ │ Prayer / Google  │

          └───────────────────┘ └──────────────────┘

Qibla calculation is performed locally on the mobile device and does not require an external Qibla API.

5

9. User Experience Overview

The primary mobile experience is designed around simplicity.

The core content hierarchy is:

General Category

       ↓

Subcategory

       ↓

Content Items

       ↓

Individual Dhikr / Dua

Example:

Azkar

 └── Morning

      ├── Al-Ikhlas ×3

      ├── Al-Falaq ×3
      └── An-Nas ×3

Only one Dhikr/Dua is displayed as the primary content at a time.

The user can:

•

Increment the counter.

•

Move to the next item.

•

Move to the previous item.

•

Play audio when available.

•

Return to the category screen.

When the final item in a Subcategory is completed, the application returns the user to the Categories

page rather than automatically entering another Subcategory.

10. Functional Requirements — Mobile

Application

10.1 Azkar & Duas

FR-AZK-01

The system shall provide users with access to different types of Azkar, Duas, acts of worship, and other

supported Islamic content organized into General Categories and Subcategories.

6

FR-AZK-02

The system shall allow users to browse available Azkar and Duas within each Subcategory.

FR-AZK-03

The system shall display the configured repetition target for applicable content.

FR-AZK-04

The system shall allow users to increment the current repetition count.

FR-AZK-05

The system shall display the current progress, including the current repetition and configured target.

FR-AZK-06

When the target is reached, the system shall mark the current Dhikr as completed and automatically
display the next Dhikr within the same Subcategory.

FR-AZK-07

The system shall allow manual Next and Previous navigation between content items within the current

Subcategory.

FR-AZK-08

The system shall display one primary Dhikr/Dua at a time.

FR-AZK-09

The system shall support an optional audio recording for a content item.

FR-AZK-10

If an audio recording is available, the system shall allow the user to play it when Internet connectivity is

available.

FR-AZK-11

If audio is available but Internet connectivity is unavailable, the system shall inform the user that audio

playback requires an Internet connection.

FR-AZK-12

If no audio recording is associated with a content item, the audio control shall not be displayed.

7

FR-AZK-13

Content shall be dynamically managed through the backend and administrative dashboard without

requiring source-code changes for normal content updates.

11. Personal Wird Requirements

FR-WIR-01

The system shall allow users to create a Personal Wird by entering the Wird text and target repetition

count.

FR-WIR-02

The system shall allow users to create multiple Personal Wird items.

FR-WIR-03

Personal Wird shall be displayed separately from predefined Islamic content.

FR-WIR-04

The user shall be able to increment the current repetition count.

FR-WIR-05

The current count shall be allowed to exceed the configured target.

FR-WIR-06

The system shall display the current count and target count.

FR-WIR-07

The user shall be able to edit a Personal Wird.

FR-WIR-08

The user shall be able to delete a Personal Wird.

FR-WIR-09

The user shall be able to reset a Personal Wird counter.

FR-WIR-10

Resetting a Personal Wird shall set its current count to zero.

8

FR-WIR-11

Personal Wird shall be available to guest users as well as authenticated users.

FR-WIR-12

Personal Wird data shall be associated with either an authenticated account or an anonymous

application-generated identity.

FR-WIR-13

The anonymous identity shall not rely on a hardware-specific device identifier unless explicitly approved

during technical implementation.

12. Prayer Times Requirements

FR-PRY-01

The application shall obtain the user's current location when location-dependent prayer information is

required.

FR-PRY-02

The system shall retrieve daily prayer times based on the user's location.

FR-PRY-03

The system shall display the available prayer times for the current day.

FR-PRY-04

The system shall identify the upcoming prayer based on the current time.

FR-PRY-05

The system shall display the remaining time until the upcoming prayer.

FR-PRY-06

Prayer times shall be retrieved through an external prayer-times service.

FR-PRY-07

The initial candidate external service is AlAdhan, subject to technical validation and production review.

FR-PRY-08

Prayer time retrieval shall require Internet connectivity.

9

FR-PRY-09

If Internet connectivity is unavailable when prayer data is required, the application shall inform the user

that an Internet connection is required.

FR-PRY-10

If the user denies location permission, the system shall not repeatedly request permission

automatically.

FR-PRY-11

When the user later accesses the relevant Prayer Times feature, the application shall provide an

appropriate mechanism for requesting/enabling location access.

13. Qibla Requirements

FR-QIB-01

The application shall use the user's current location to determine the Qibla direction.

FR-QIB-02

Qibla shall be calculated locally on the mobile device.

FR-QIB-03

The Qibla feature shall use the fixed coordinates of the Kaaba as the destination reference.

FR-QIB-04

The system shall use supported device orientation/sensor capabilities to provide a visual Qibla

indication.

FR-QIB-05

The Qibla feature shall not require an external Qibla API.

FR-QIB-06

The Qibla feature shall remain usable without Internet connectivity, provided the required location and

device sensor capabilities are available.

FR-QIB-07

If required location or sensor capabilities are unavailable, the application shall provide appropriate

feedback.

10

14. User Account & Authentication

Requirements

FR-ACT-01

The core application shall be usable without creating an account.

FR-ACT-02

The system shall support user account registration and authentication.

FR-ACT-03

Registration shall require:

•

Name.

•

phone.

•

Email.

•

Age.

FR-ACT-04

Phone number shall be optional.

FR-ACT-05

The system shall support Google Sign-In.

FR-ACT-06

Google authentication shall be integrated with the Laravel backend rather than Firebase Authentication.

FR-ACT-07

Personalized authenticated user data shall be associated with the corresponding account.

FR-ACT-08

Account-dependent tracking shall require authentication.

FR-ACT-09

Guest users shall not have authenticated historical tracking data.

FR-ACT-10

When a guest user creates an account, eligible Personal Wird data created during the guest session

shall be transferred/associated with the newly created account.

11

FR-ACT-11

The exact technical mechanism for guest-data migration shall be determined during Technical Analysis.

15. Tracking Requirements

Tracking is not part of the core MVP user experience but the system shall be designed to support future

tracking capabilities.

FR-TRK-01

The system may provide historical tracking for authenticated users.

FR-TRK-02

The system may track activity related to predefined Azkar.

FR-TRK-03

The system may track activity related to Personal Wird.

FR-TRK-04

The system may provide historical usage information.

FR-TRK-05

The system may provide trends over selected periods.

Advanced tracking, statistics, trends, and streaks are considered future enhancements unless explicitly

included in the final MVP implementation plan.

16. Administrative Dashboard Functional

Requirements

16.1 User Management & Overview

FR-ADM-01

The dashboard shall allow authorized administrators to view the total number of users accessing the

application.

FR-ADM-02

The dashboard shall allow authorized administrators to view registered users.

12

FR-ADM-03

The dashboard shall allow authorized administrators to view guest users.

16.2 Roles & Permissions

FR-ADM-04

The dashboard shall allow authorized administrators to view roles.

FR-ADM-05

The dashboard shall allow authorized administrators to create roles.

FR-ADM-06

The dashboard shall allow authorized administrators to modify roles.

FR-ADM-07

The dashboard shall allow authorized administrators to delete roles where permitted.

FR-ADM-08

The dashboard shall allow permissions to be assigned to roles.

FR-ADM-09

The system shall enforce role-based permissions for dashboard operations.

The exact role and permission model will be defined during Technical Analysis.

17. Category Management

FR-ADM-10

The dashboard shall allow authorized administrators to view General Categories.

FR-ADM-11

The dashboard shall allow authorized administrators to create General Categories.

FR-ADM-12

The dashboard shall allow authorized administrators to modify General Categories.

13

FR-ADM-13

The dashboard shall allow authorized administrators to delete General Categories when business rules

permit.

18. Subcategory Management

FR-ADM-14

The dashboard shall allow authorized administrators to view Subcategories.

FR-ADM-15

The dashboard shall allow authorized administrators to create Subcategories.

FR-ADM-16

The dashboard shall allow authorized administrators to modify Subcategories.

FR-ADM-17

The dashboard shall allow authorized administrators to delete Subcategories when business rules

permit.

19. Content Management

FR-ADM-18

The dashboard shall allow authorized administrators to view Dhikr/Dua content.

FR-ADM-19

The dashboard shall allow authorized administrators to create content.

FR-ADM-20

The dashboard shall allow authorized administrators to modify content.

FR-ADM-21

The dashboard shall allow authorized administrators to delete content.

14

FR-ADM-22

The dashboard shall allow authorized administrators to manage:

•

Title.

•

Text.

•

Repetition count.

•

Icon.

•

Source/Takhrij.

•

Virtue/Fadl.

•

Explanation.

•

Description.

•

Audio.

•

Publication status.

20. Content Publication

The system shall support content publication states.

FR-ADM-23

Administrators shall be able to mark content as Draft.

FR-ADM-24

Administrators shall be able to mark content as Published.

FR-ADM-25

Draft content shall not be displayed to regular mobile users.

FR-ADM-26

Published content shall be available to users according to the application's content-delivery rules.

21. Dashboard Analytics

FR-ADM-27

Authorized administrators shall be able to access dashboard analytics.

FR-ADM-28

The dashboard may provide metrics related to users and application usage.

15

FR-ADM-29

The exact analytics metrics, aggregation rules, and retention policy shall be defined during Technical

Analysis.

The MVP shall use application/backend data through Laravel/MySQL and shall not depend on Firebase

Analytics.

22. External Service Information

FR-ADM-30

The dashboard may provide authorized administrators with relevant external-service information.

FR-ADM-31

The dashboard may provide relevant prayer-service information and status where technically

applicable.

FR-ADM-32

Qibla-related information shall reflect the local calculation architecture rather than depending on an

external Qibla service.

FR-ADM-33

Relevant external-service failures shall be surfaced appropriately where operational visibility is required.

23. Non-Functional Requirements — Mobile

23.1 Performance

NFR-PER-01

The application shall provide a responsive user experience.

NFR-PER-02

The application shall minimize unnecessary loading and processing.

NFR-PER-03

Counter increments shall occur without noticeable delay during normal operation.

16

23.2 Usability

NFR-USA-01

Core functionality shall be simple and intuitive.

NFR-USA-02

One Dhikr/Dua shall remain the primary content displayed at a time.

NFR-USA-03

The application shall provide clear feedback when a count is incremented or completed.

NFR-USA-04

Next and Previous controls shall have clear and understandable behavior.

23.3 Accessibility

NFR-ACC-01

The application shall support adjustable text sizes.

NFR-ACC-02

The interface shall provide sufficient visual contrast.

NFR-ACC-03

The application shall support Light and Dark modes.

NFR-ACC-04

Interactive elements shall provide sufficiently large touch targets.

23.4 Reliability

NFR-REL-01

The system shall maintain current progress without unintended loss during normal usage.

NFR-REL-02

External service failures shall not cause the application to crash.

17

NFR-REL-03

The application shall provide appropriate feedback when required external data is unavailable.

23.5 Security & Privacy

NFR-SEC-01

Authentication credentials and authentication flows shall be handled securely.

NFR-SEC-02

Personal user data shall only be accessible to the authorized owner and authorized system

administrators according to permissions.

NFR-SEC-03

The application shall request device permissions only when required.

NFR-SEC-04

Precise location shall not be persistently stored unless technically necessary and explicitly approved.

23.6 Maintainability

NFR-MNT-01

The application shall follow a modular architecture.

NFR-MNT-02

New categories, subcategories, and content shall be addable without modifying application source

code.

NFR-MNT-03

Content management shall remain appropriately separated from presentation and business logic.

23.7 Compatibility

NFR-CMP-01

The application shall support defined Android and iOS versions established during Technical Planning.

18

NFR-CMP-02

The interface shall work consistently across supported screen sizes.

NFR-CMP-03

The application shall handle supported orientation and system settings appropriately.

23.8 External Services

NFR-EXT-01

The application shall handle external-service failures and timeouts gracefully.

NFR-EXT-02

The application shall provide appropriate feedback when location-dependent services cannot operate.

NFR-EXT-03

The application shall minimize unnecessary external API requests.

23.9 Scalability

NFR-SCL-01

The system shall support adding new content without fundamental architectural redesign.

NFR-SCL-02

The system shall support increasing numbers of users and Personal Wird records without fundamental

architectural redesign.

24. Non-Functional Requirements — Admin

Dashboard

Performance

NFR-ADM-PER-01

Dashboard pages shall provide responsive interaction during normal usage.

19

NFR-ADM-PER-02

The dashboard shall minimize unnecessary API requests.

NFR-ADM-PER-03

Large datasets shall use appropriate pagination/filtering mechanisms where required.

NFR-ADM-PER-04

CRUD operations shall provide timely feedback.

Usability

NFR-ADM-USA-01

Dashboard navigation shall be clear.

NFR-ADM-USA-02

CRUD operations shall provide clear success and failure feedback.

NFR-ADM-USA-03

Destructive operations shall require appropriate confirmation.

NFR-ADM-USA-04

Tables shall provide suitable filtering/searching where required.

NFR-ADM-USA-05

The dashboard shall clearly communicate role/permission limitations.

Accessibility

NFR-ADM-ACC-01

Dashboard text shall remain readable.

NFR-ADM-ACC-02

Interactive controls shall have clear states.

NFR-ADM-ACC-03

The interface shall maintain sufficient contrast.

20

NFR-ADM-ACC-04

Keyboard and common accessibility interactions should be supported where applicable.

Security

NFR-ADM-SEC-01

Dashboard access shall require authentication.

NFR-ADM-SEC-02

Dashboard operations shall be controlled by permissions.

NFR-ADM-SEC-03

Unauthorized users shall not access restricted operations.

NFR-ADM-SEC-04

Sensitive administrative operations shall be appropriately protected.

NFR-ADM-SEC-05

The backend shall enforce authorization rather than relying solely on frontend restrictions.

Reliability

NFR-ADM-REL-01

Dashboard failures shall not corrupt existing content.

NFR-ADM-REL-02

CRUD failures shall provide actionable feedback.

NFR-ADM-REL-03

External service failures shall be handled gracefully.

NFR-ADM-REL-04

The dashboard shall maintain data consistency during normal operations.

Maintainability

NFR-ADM-MNT-01

Dashboard modules shall be logically separated.

21

NFR-ADM-MNT-02

Reusable UI and business components should be used where appropriate.

NFR-ADM-MNT-03

Content-management functionality shall be extensible.

Compatibility

NFR-ADM-CMP-01

The dashboard shall support modern desktop browsers defined during Technical Planning.

NFR-ADM-CMP-02

The dashboard shall provide a responsive experience for supported screen sizes.

NFR-ADM-CMP-03

Browser-specific behavior shall be considered during QA.

External Services

NFR-ADM-EXT-01

The dashboard shall handle external service failures.

NFR-ADM-EXT-02

External-service information shall not expose sensitive credentials.

NFR-ADM-EXT-03

External service requests shall be minimized and appropriately monitored.

Scalability

NFR-ADM-SCL-01

The dashboard shall support increasing content volume.

NFR-ADM-SCL-02

The dashboard shall support increasing user volume.

NFR-ADM-SCL-03

The permission system shall support additional roles and permissions in the future.

22

25. Business Rules

25.1 Counter Rules

BR-CTR-01

A Dhikr counter starts at zero.

BR-CTR-02

Only the designated interaction shall increment the counter.

BR-CTR-03

A predefined Dhikr counter shall not decrease during normal counting.

BR-CTR-04

Reaching the target marks the Dhikr as completed.

BR-CTR-05

Completion automatically advances to the next Dhikr within the same Subcategory.

BR-CTR-06

Additional increments shall not be allowed after the target is reached.

25.2 Personal Wird Rules

BR-WIR-01

Personal Wird text is required.

BR-WIR-02

A valid target count is required.

BR-WIR-03

The current count may exceed the target.

BR-WIR-04

Users may add, edit, and delete Personal Wird.

23

BR-WIR-05

Users may reset Personal Wird counters.

BR-WIR-06

Resetting sets the current count to zero.

25.3 Content Hierarchy Rules

BR-CNT-01

Each Subcategory belongs to exactly one General Category.

BR-CNT-02

Each content item belongs to exactly one Subcategory.

BR-CNT-03

A General Category cannot be deleted while dependent Subcategories exist unless an approved

cascading/dependency policy is implemented.

BR-CNT-04

Each content item must contain the required content information.

25.4 Predefined Content Rules

BR-CNT-05

Required content information includes:

•

Title.

•

Text.

•

Description.

•

Source/Takhrij.

•

Virtue/Fadl.

BR-CNT-06

Audio is optional.

BR-CNT-07

Repetition count is configurable where applicable.

24

BR-CNT-08

Content supports Draft and Published states.

BR-CNT-09

Draft content shall not be shown to regular users.

BR-CNT-10

Published content shall be available according to content-delivery rules.

25.5 Guest & Account Rules

BR-ACC-01

Core application functionality does not require an account.

BR-ACC-02

Accounts are required only for account-dependent functionality.

BR-ACC-03

Authenticated tracking requires a registered account.

BR-ACC-04

Guest Personal Wird data may exist under an anonymous application-generated identity.

BR-ACC-05

When the guest creates an account, eligible guest Personal Wird data shall be associated with the newly

created account.

BR-ACC-06

The technical implementation of guest-to-account migration shall be defined during Technical Analysis.

25.6 Roles & Permissions

BR-ROL-01

Dashboard access shall be controlled through roles and permissions.

BR-ROL-02

Administrators may perform only operations permitted by their assigned permissions.

25

BR-ROL-03

The exact role and permission structure remains subject to Technical Analysis.

25.7 Location Rules

BR-LOC-01

Location-dependent features use the current location when permission is available.

BR-LOC-02

The application shall not repeatedly request denied location permission automatically.

BR-LOC-03

A relevant feature page shall provide the user with a way to request/enable location access again.

BR-LOC-04

The application shall minimize persistent storage of precise location.

25.8 Analytics Rules

BR-ANA-01

Dashboard analytics shall only be available to authorized administrators.

BR-ANA-02

Analytics metrics shall distinguish relevant registered and guest data where applicable.

BR-ANA-03

Exact metric definitions, calculations, aggregation, and retention shall be established during Technical

Analysis.

26. Data Requirements

26.1 General

DR-01

The system shall maintain data required for core application functionality.

26

DR-02

The system shall maintain relationships between categories, subcategories, content, users, Personal

Wird, roles, permissions, and tracking data.

DR-03

Data shall be associated with the appropriate entity or context.

DR-04

Authorized users shall be able to perform applicable CRUD operations.

26.2 User Data

DR-USR-01

Each registered user shall have a unique user ID.

DR-USR-02

The system shall store required authentication/account information.

DR-USR-03

The system shall maintain account status.

DR-USR-04

The system shall distinguish registered users from guest/anonymous users where required.

DR-USR-05

The system shall store only information necessary for the product's requirements.

26.3 General Category

DR-CAT-01

Each category shall have a unique ID.

DR-CAT-02

Each category shall have a title/name.

DR-CAT-03

Each category may have a description.

27

DR-CAT-04

Each category may have an icon.

DR-CAT-05

Additional metadata may be supported where required.

DR-CAT-06

A category may contain multiple Subcategories.

DR-CAT-07

Category deletion shall respect dependency rules.

26.4 Subcategory

DR-SUB-01

Each Subcategory shall have a unique ID.

DR-SUB-02

Each Subcategory shall have a title/name.

DR-SUB-03

Each Subcategory may have a description.

DR-SUB-04

Each Subcategory may have an icon.

DR-SUB-05

Each Subcategory shall belong to exactly one General Category.

DR-SUB-06

A Subcategory may contain multiple content items.

26.5 Content

DR-CNT-01

Each content item shall have a unique ID.

28

DR-CNT-02

Each content item shall belong to exactly one Subcategory.

DR-CNT-03

Content shall have a title.

DR-CNT-04

Content shall contain the Dhikr/Dua text.

DR-CNT-05

Content shall contain a description.

DR-CNT-06

Content shall contain Source/Takhrij information.

DR-CNT-07

Content shall contain Virtue/Fadl information.

DR-CNT-08

Content may have a configured target repetition count where applicable.

DR-CNT-09

Content may have an associated audio recording.

DR-CNT-10

Content may contain additional metadata.

DR-CNT-11

Content shall have a publication status.

DR-CNT-12

Supported publication states include Draft and Published.

DR-CNT-13

The system shall distinguish content that is available to users from unpublished content.

29

26.6 Personal Wird

DR-WIR-01

Each Personal Wird shall have a unique ID.

DR-WIR-02

Each Personal Wird shall have an owner/identity association.

DR-WIR-03

The owner may be an authenticated user or anonymous application-generated identity.

DR-WIR-04

The Personal Wird shall contain text.

DR-WIR-05

The Personal Wird shall contain a target count.

DR-WIR-06

The Personal Wird may contain the current count.

DR-WIR-07

The current count may exceed the target.

DR-WIR-08

The system shall support edit, delete, and reset operations.

DR-WIR-09

Anonymous identity shall be generated by the application/system.

DR-WIR-10

Hardware-specific device identifiers shall not be required for ownership.

DR-WIR-11

Persistence, synchronization, and migration details shall be finalized during Technical Analysis.

30

26.7 Tracking

DR-TRK-01

Tracking data shall be associated with authenticated users.

DR-TRK-02

Tracking data shall be associated with the corresponding user.

DR-TRK-03

Tracking may reference predefined or Personal Wird activity.

DR-TRK-04

Tracking data shall contain sufficient information to determine activity over time.

DR-TRK-05

The data model shall support historical information.

DR-TRK-06

Guest users shall not receive authenticated tracking history.

DR-TRK-07

Guest-to-account tracking behavior shall be explicitly defined during technical implementation.

26.8 Roles & Permissions

DR-ROL-01

Each role shall have a unique ID.

DR-ROL-02

Each role shall have a name.

DR-ROL-03

Roles shall be associated with permissions.

DR-ROL-04

Permissions shall define allowed dashboard operations.

31

DR-ROL-05

The system shall support the required relationship between administrators, roles, and permissions.

DR-ROL-06

The exact implementation structure remains subject to Technical Analysis.

26.9 Analytics

DR-ANA-01

The system shall maintain data required for defined analytics.

DR-ANA-02

Analytics shall distinguish registered and guest users where applicable.

DR-ANA-03

Analytics may include usage information.

DR-ANA-04

Metric definitions, aggregation, and retention remain subject to Technical Analysis.

26.10 External Service Data

DR-EXT-01

The system shall process prayer-time data returned by the selected external service.

DR-EXT-02

The system shall support the data required for local Qibla calculation.

DR-EXT-03

External service data shall remain distinguishable from application-owned data.

DR-EXT-04

Exact storage and caching strategy shall be defined during Technical Analysis.

32

26.11 Location

DR-LOC-01

The system may process location information for Prayer Times and Qibla.

DR-LOC-02

Precise location persistence shall be minimized.

DR-LOC-03

Exact location storage/caching behavior shall be determined during Technical Analysis.

27. Data Relationships

The high-level relationships are:

General Category

      │

      └────── 1 : N ────── Subcategory

                              │

                              └──── 1 : N ──── Content

User

 │

 ├──── 1 : N ──── Personal Wird

 │

 └──── 1 : N ──── Tracking

Role

 │

 └──── N : M ──── Permission

28. Validation Requirements

DR-VAL-01

Required fields shall be validated.

DR-VAL-02

Published content shall not contain missing required information.

33

DR-VAL-03

Relationships between entities shall be validated.

DR-VAL-04

Repetition targets shall be validated.

DR-VAL-05

Unauthorized CRUD operations shall be rejected by the backend.

29. Use Cases

UC-01 — Browse and Complete Dhikr

Actor: Guest / Authenticated User

Flow:

1.

User opens the application.

2.

User selects a General Category.

3.

User selects a Subcategory.

4.

User opens a Dhikr.

5.

System displays the Dhikr and target count.

6.

User increments the counter.

7.

System updates progress.

8.

Target is reached.

9.

System marks the Dhikr complete.

10.

System automatically displays the next Dhikr within the same Subcategory.

11.

After completing the final Dhikr, system returns to Categories.

UC-02 — Create Personal Wird

Actor: Guest / Authenticated User

1.

User opens Personal Wird.

2.

User selects Create.

3.

User enters Wird text.

4.

User enters target count.

5.

System validates the input.

6.

System creates the Personal Wird.

34

UC-03 — Track Personal Wird

Actor: Guest / Authenticated User

1.

User selects a Personal Wird.

2.

System displays current count and target.

3.

User increments the counter.

4.

System updates the count.

5.

Count may exceed the target.

6.

User may reset the count when desired.

UC-04 — View Prayer Times

Actor: Guest / Authenticated User

1.

User opens Prayer Times.

2.

System checks location availability.

3.

If permission is missing, system requests/enables location access.

4.

System retrieves prayer times from the external service.

5.

System displays daily prayer times.

6.

System identifies upcoming prayer.

7.

System displays countdown.

UC-05 — Determine Qibla

Actor: Guest / Authenticated User

1.

User opens Qibla.

2.

System obtains required location.

3.

System calculates Qibla direction locally.

4.

System uses supported device sensors.

5.

System displays the Qibla indicator.

The feature does not require Internet connectivity.

UC-06 — Create User Account

Actor: Guest

1.

Guest selects account creation.

2.

User enters required information.

3.

System validates the information.

4.

System creates the account.

5.

Eligible guest Personal Wird data is associated with the new account.

35

UC-07 — Manage General Categories

Actor: Authorized Administrator

The administrator can:

•

View.

•

Create.

•

Edit.

•

Delete categories where permitted.

UC-08 — Manage Subcategories

Actor: Authorized Administrator

The administrator can:

•

View.

•

Create.

•

Edit.

•

Delete Subcategories where permitted.

UC-09 — Manage Dhikr/Dua Content

Actor: Authorized Administrator

The administrator can:

•

Create content.

•

Edit content.

•

Delete content.

•

Configure repetition.

•

Add source information.

•

Add virtue.

•

Add explanation.

•

Add description.

•

Associate audio.

•

Change publication state.

UC-10 — Manage Roles & Permissions

Actor: Authorized Administrator

36

The administrator can:

•

View roles.

•

Create roles.

•

Edit roles.

•

Assign permissions.

•

Manage authorized dashboard access.

UC-11 — View Analytics

Actor: Authorized Administrator

1.

Administrator opens Analytics.

2.

System verifies permissions.

3.

System retrieves available analytics data.

4.

Dashboard displays supported metrics.

UC-12 — View User Overview

Actor: Authorized Administrator

The administrator can view:

•

Total users.

•

Registered users.

•

Guest users.

UC-13 — View External Service Information

Actor: Authorized Administrator

The administrator may view relevant external service information and operational status where

supported.

30. System Constraints

Platform

SC-01

The mobile application shall target Android and iOS.

37

SC-02

The administrative interface shall be web-based.

SC-03

The system shall consist of mobile application, dashboard, backend, and database components.

Backend

SC-04

The backend shall use Laravel.

SC-05

The mobile application and dashboard shall communicate with the backend through APIs.

SC-06

The mobile application and dashboard shall not directly access the database.

Content

SC-07

Application content shall be dynamically managed through the backend/dashboard.

SC-08

Normal content changes shall not require source-code changes or a new mobile application release.

SC-09

The application owner shall be responsible for reviewing and validating religious content before

publishing.

Online / Offline

SC-10

Primary Categories, Subcategories, and Content shall be retrieved from backend APIs.

SC-11

Supported content shall be stored locally to allow offline access after initial retrieval.

SC-12

Audio shall not be included in the offline content package.

38

SC-13

Prayer times require Internet connectivity.

SC-14

Supported data shall synchronize automatically when connectivity is available without requiring

unnecessary manual action.

SC-15

Supported offline content shall remain usable without Internet access.

Personal Wird

SC-16

Personal Wird shall be managed through backend APIs and supported local storage.

SC-17

Personal Wird shall persist after application close/reopen according to the defined persistence strategy.

SC-18

The current repetition counter is not required to persist between sessions and may start from zero in a

new session.

SC-19

Personal Wird ownership shall use authenticated accounts or an anonymous application-generated

identity.

Qibla

SC-20

Qibla shall operate offline.

SC-21

Qibla shall depend on location and supported device capabilities.

SC-22

Exact Qibla calculation and sensor handling shall be defined during Technical Analysis.

39

Location

SC-23

Location shall be requested when location-dependent information is required.

SC-24

Location shall be used for Prayer Times and Qibla.

SC-25

Precise location shall not be persistently stored unless required and approved.

Account

SC-26

Core application functionality shall not require an account.

SC-27

Authentication shall be required only for account-dependent functionality.

SC-28

Dashboard access shall require authentication.

SC-29

Dashboard access shall be controlled through roles and permissions.

Third Party

SC-30

External service failures shall be handled gracefully.

SC-31

Exact external services, API contracts, caching, authentication, storage, and infrastructure dependencies

shall be finalized during Technical Analysis.

40

31. External Integrations & Dependencies

31.1 Laravel Backend

EXT-01

Laravel shall provide backend APIs and business logic.

EXT-02

The backend shall be responsible for:

•

Authentication.

•

Users.

•

Content.

•

Personal Wird.

•

Tracking.

•

Roles.

•

Permissions.

•

Analytics.

•

Relevant service integrations.

31.2 Database

EXT-03

The system shall use MySQL.

EXT-04

The initial database infrastructure is planned to use the same Hostinger infrastructure as the Laravel

backend.

31.3 Hosting

EXT-05

The Laravel backend, MySQL database, and audio assets are initially planned to be hosted using

Hostinger infrastructure.

EXT-06

Exact infrastructure configuration shall be determined during Technical Planning.

41

31.4 Prayer Service

EXT-07

The application shall integrate with an external prayer-times service.

EXT-08

AlAdhan is the initial candidate, subject to technical validation and production review.

EXT-09

The application shall handle service failures, unavailable responses, and timeouts gracefully.

31.5 Google OAuth

EXT-10

The application shall support Google authentication.

EXT-11

Google authentication shall be integrated with Laravel.

EXT-12

Firebase Authentication shall not be required for the MVP.

31.6 Device Location

EXT-13

The mobile application shall use native Android/iOS location capabilities.

EXT-14

Location shall be used for Prayer Times and Qibla.

31.7 Qibla

EXT-15

The application shall not depend on an external Qibla API.

42

EXT-16

Qibla shall be calculated locally using location, Kaaba coordinates, and device sensor capabilities.

31.8 Audio

EXT-17

Audio files are initially planned to be stored on the same Hostinger infrastructure.

EXT-18

Audio playback requires Internet connectivity.

31.9 Application Distribution

EXT-19

The Android application shall be distributed through Google Play.

EXT-20

The iOS application shall be distributed through the Apple App Store.

32. Assumptions

ASM-01

The first successful application setup/content retrieval assumes Internet connectivity.

ASM-02

If the application is opened for the first time without Internet access and no local data is available, the

user shall be informed that Internet connectivity is required for initial setup/content retrieval.

ASM-03

After initial retrieval, supported content may be available offline.

ASM-04

The application assumes supported Android/iOS devices provide required location and sensor

capabilities.

43

ASM-05

Exact supported OS versions and device compatibility shall be defined during Technical Planning.

ASM-06

Location will be available when the user needs Prayer Times or Qibla.

ASM-07

If location permission is denied, the application will not repeatedly request permission automatically.

ASM-08

The user may later request/enable location from the relevant feature.

ASM-09

The Laravel backend and required services are assumed to be available during normal operation.

ASM-10

Service failures shall be handled through appropriate error/offline mechanisms.

ASM-11

The application owner is responsible for reviewing and validating religious content before publication.

ASM-12

The system manages, stores, and displays religious content but does not independently determine its

religious authenticity.

33. Out of Scope — MVP

The following features are explicitly excluded from the MVP.

33.1 Notifications

OOS-01

Push notifications are not included in the MVP.

OOS-02

Prayer reminders are not included.

44

OOS-03

Azkar reminders are not included.

OOS-04

Personal Wird reminders are not included.

33.2 Advanced Statistics & Gamification

OOS-05

Advanced mobile statistics are excluded.

OOS-06

Streaks are excluded.

OOS-07

Achievements are excluded.

OOS-08

Advanced trends and gamification are excluded.

33.3 Social Features

OOS-09

Friends/following functionality is excluded.

OOS-10

Leaderboards are excluded.

OOS-11

Social challenges are excluded.

OOS-12

Sharing achievements is excluded.

OOS-13

Sharing Personal Wird is excluded.

45

33.4 Offline Audio

OOS-14

Downloading audio for offline playback is excluded.

OOS-15

Offline audio storage is excluded.

Audio playback in the MVP requires Internet connectivity.

33.5 Additional Islamic Content

The following are outside the MVP:

•

Islamic articles.

•

Hadith library.

•

Seerah.

•

Fiqh content.

•

Names of Allah.

•

Ruqyah.

•

Ramadan-specific functionality.

33.6 Quran

Quran functionality is excluded from the MVP, including:

•

Quran reading.

•

Quran audio.

•

Tafsir.

•

Translations.

•

Bookmarks.

•

Khatma functionality.

•

Quran-related tracking.

33.7 Languages

OOS-16

The MVP will support Arabic.

OOS-17

Additional languages are excluded from the MVP.

46

33.8 Wearables & Device Integrations

OOS-18

Apple Watch support is excluded.

OOS-19

Wear OS support is excluded.

OOS-20

Home-screen widgets and external device integrations are excluded.

33.9 User-Facing Web Application

OOS-21

A user-facing web application is excluded.

OOS-22

The web component is limited to the administrative dashboard.

33.10 Monetization

OOS-23

Paid subscriptions are excluded from the MVP.

OOS-24

In-App Purchases are excluded.

OOS-25

Advertising is included in the MVP.

Ads shall appear in the designated bottom-screen area.

47

34. Acceptance Criteria

34.1 Browse Content

AC-AZK-01

The user shall be able to navigate:

General Category

      ↓

Subcategory

      ↓

Content Items

Navigation shall be simple and intuitive.

34.2 Dhikr Counter

AC-AZK-02

•

Counter starts at zero.

•

Valid user interaction increments by one.

•

Progress updates immediately.

•

Reaching the target marks the item complete.

•

The next Dhikr is displayed automatically.

•

Additional increments after completion are prevented.

34.3 Next / Previous

AC-AZK-03

•

Next moves within the current Subcategory.

•

Previous moves within the current Subcategory.

•

Navigation does not automatically enter another Subcategory.

•

Completing the final item returns the user to the Categories page.

34.4 Audio

AC-AZK-04

•

If audio exists, an audio control is displayed.

•

Online playback is supported.

•

Offline playback displays an Internet-required message.

•

If no audio exists, no audio control is displayed.

48

34.5 Personal Wird

AC-WIR-01

The user can:

•

Enter Wird text.

•

Specify target count.

•

Create multiple Wird items.

•

Increment the count.

•

Exceed the target.

•

Edit the Wird.

•

Delete the Wird.

•

Reset the counter.

Reset sets the current count to zero.

34.6 Prayer Times

AC-PRY-01

•

Location permission is requested when required.

•

Granted location allows prayer-time retrieval.

•

Daily prayer times are displayed.

•

Upcoming prayer is identified.

•

Countdown is displayed.

•

Internet is required for prayer-time retrieval.

•

If Internet is unavailable, the user receives an appropriate message.

•

If location is denied, the system does not repeatedly request it automatically.

•

The user can request/enable location again from the relevant Prayer Times experience.

34.7 Qibla

AC-QIB-01

•

Current location is used.

•

Qibla is calculated locally.

•

Kaaba coordinates are used as the destination.

•

Device orientation/sensor information is used.

•

The Qibla feature works without Internet.

•

Missing location/sensor capabilities result in appropriate feedback.

49

34.8 Account

AC-ACT-01

Core application features are available to guests.

AC-ACT-02

Registration requires:

•

Name.

•

Email.

•

Age.

Phone number is optional.

AC-ACT-03

Google Sign-In is supported.

AC-ACT-04

Account-dependent tracking requires authentication.

AC-ACT-05

When a guest creates an account, eligible Personal Wird data is associated with the new account.

The exact technical migration mechanism shall be defined during Technical Analysis.

34.9 General Acceptance Conditions

AC-GEN-01

The system shall provide clear feedback when required permissions, Internet connectivity, external

services, or device capabilities are unavailable.

AC-GEN-02

External-service failures shall not crash the application.

AC-GEN-03

Counter and navigation interactions shall provide immediate understandable feedback.

AC-GEN-04

The acceptance criteria shall be validated on the supported Android and iOS versions defined during

Technical Planning.

50

35. Future Enhancements

The following features are candidates for future versions and are not part of the MVP.

35.1 Notifications & Reminders

Potential future functionality includes:

•

Prayer notifications.

•

Prayer reminders.

•

Morning Azkar reminders.

•

Evening Azkar reminders.

•

Personal Wird reminders.

•

Custom notification schedules.

•

Notification preferences.

35.2 Advanced Tracking & Statistics

Potential functionality:

•

Detailed historical tracking.

•

Daily statistics.

•

Weekly statistics.

•

Monthly statistics.

•

Progress trends.

•

Streaks.

•

Personal usage insights.

35.3 Gamification

Potential functionality:

•

Goals.

•

Milestones.

•

Achievement systems.

•

Streak-based experiences.

•

Optional progress rewards.

35.4 Additional Islamic Content

Potential future content includes:

•

Quran.

51

•

Hadith.

•

Seerah.

•

Fiqh.

•

Names of Allah.

•

Ruqyah.

•

Ramadan-specific features.

35.5 Additional Languages

Potential future support for:

•

English.

•

Additional languages.

35.6 Social Features

Potential future functionality:

•

Sharing selected content.

•

Sharing achievements.

•

Social challenges.

•

Friends/community.

•

Leaderboards.

35.7 Offline Audio

Potential future functionality:

•

Download selected recordings.

•

Store recordings locally.

•

Play downloaded audio offline.

35.8 Wearables & Device Integrations

Potential future functionality:

•

Apple Watch.

•

Wear OS.

•

Widgets.

•

Other supported device integrations.

52

35.9 Monetization

Potential future options include:

•

Premium subscriptions.

•

In-App Purchases.

•

Additional monetization models.

35.10 Personalization

Potential future functionality includes:

•

Advanced Personal Wird customization.

•

Configurable worship routines.

•

Personalized home experience.

•

User-specific preferences.

36. Product Content & Religious Validation

Because the application contains Islamic religious content, content quality and validation are important

product responsibilities.

The system shall provide structured fields for:

•

Source/Takhrij.

•

Virtue/Fadl.

•

Explanation.

•

Description.

The technical system shall not independently claim that a religious narration or practice is authentic.

The product owner/content team shall be responsible for reviewing and validating content before

publishing it to users.

The administrative workflow should therefore support a Draft → Review → Publish process where
appropriate.

37. Privacy Considerations

The application may process:

•

Account information.

•

Personal Wird.

•

Usage information.

53

•

Location information.

The system should follow data minimization principles.

In particular:

•

Location should only be requested when required.

•

Precise location should not be stored unnecessarily.

•

Personal data should be protected by backend authorization.

•

Guest identities should use application-generated identifiers rather than hardware-specific

identifiers.

•

Authentication credentials should never be exposed to the client unnecessarily.

•

Administrative access must be permission-controlled.

Detailed privacy-policy requirements and legal/compliance requirements should be reviewed before

production release.

38. MVP Definition

The MVP consists of the following core capabilities:

Mobile

•

Categories.

•

Subcategories.

•

Azkar/Duas.

•

Repetition counter.

•

Automatic progression.

•

Next/Previous navigation.

•

Audio playback when available and online.

•

Personal Wird.

•

Guest access.

•

Account registration/login.

•

Google Sign-In.

•

Prayer Times.

•

Upcoming Prayer.

•

Countdown.

•

Qibla.

•

Offline supported content.

•

Light/Dark mode.

•

Adjustable text size.

•

Ads.

Backend

•

Laravel.

•

REST/API layer.

•

Authentication.

•

User management.

•

Content management.

54

•

Personal Wird.

•

Guest identity.

•

Roles and permissions.

•

Required analytics data.

•

External service integration.

Dashboard

•

Authentication.

•

User overview.

•

Categories.

•

Subcategories.

•

Content.

•

Audio.

•

Draft/Published.

•

Roles.

•

Permissions.

•

Analytics.

Infrastructure

•

MySQL.

•

Hostinger initially.

•

Google OAuth.

•

Prayer-times external API.

•

Native location.

•

Local Qibla calculation.

•

Google Play distribution.

•

Apple App Store distribution.

39. Technical Decisions Deferred to Technical

Analysis

The following requirements are intentionally defined at the product/SRS level and shall be resolved

during the Technical Analysis phase:

1.

Exact mobile architecture.

2.

React Native architecture and project structure.

3.

State-management strategy.

4.

API architecture and endpoint design.

5.

Authentication/token strategy.

6.

Google OAuth implementation details.

7.

Guest identity generation and lifecycle.

8.

Guest-to-account Personal Wird migration mechanism.

9.

Offline storage technology.

10.

API caching strategy.

11.

Content synchronization strategy.

12.

Conflict resolution.

55

13.

Prayer API integration details.

14.

Prayer calculation method configuration.

15.

Qibla calculation algorithm.

16.

Compass/sensor implementation.

17.

Exact supported Android/iOS versions.

18.

Exact database schema.

19.

Laravel architecture.

20.

MySQL schema and indexes.

21.

Audio storage and delivery strategy.

22.

Dashboard architecture.

23.

Roles/permissions implementation.

24.

Analytics schema and calculations.

25.

Ads SDK and placement implementation.

26.

Error handling strategy.

27.

Logging and monitoring.

28.

Security implementation.

29.

Deployment architecture.

30.

CI/CD strategy.

31.

Testing strategy.

32.

Performance optimization strategy.

40. Requirements Traceability Overview

The requirements can be traced through the following product flow:

Product Problem

      ↓

Product Goals

      ↓

Target Users

      ↓

Features

      ↓

Functional Requirements

      ↓
Business Rules

      ↓

Data Requirements

      ↓

Use Cases

      ↓

Acceptance Criteria

      ↓

Technical Architecture

      ↓

Implementation

      ↓

Testing / QA

56

      ↓

Release

This ensures that implementation decisions remain connected to the original product problem rather

than being driven only by technical preferences.

41. SRS Status

Current Version: 1.0

Status: Requirements Complete — Pending Final Tech Lead Review

The requirements defined in this document represent the agreed MVP product scope based on the

current product discussions.

Before implementation begins, the SRS should undergo a final technical review to identify:

•

Contradictions.

•

Missing requirements.

•

Ambiguous requirements.

•

Security concerns.

•

Data-model concerns.

•

Offline/synchronization edge cases.

•

API limitations.

•

Platform limitations.

•

Performance concerns.

•

Production risks.

After final approval, the project can proceed to:

Phase 5 — UX & Information Architecture.

57

