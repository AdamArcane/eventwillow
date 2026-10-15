# Newsletters

Send branded emails to your audience.

## Overview

 A newsletter is an email you compose and send to a schedule's audience: its followers, its email subscribers, its ticket buyers, or a list of your own. Open **Newsletters** in the sidebar. Newsletters are on every plan, with a [monthly allowance](#allowance) that counts recipients.

 ![The Newsletters page for one schedule, with the Create Newsletter button and an empty list](../../../public/images/docs/newsletters--list.png)

 The section is one page with four tabs, which become a dropdown on a phone. **Create Newsletter** sits at the end of the title row on every tab. If you manage more than one schedule, a schedule picker sits beside it: each schedule has its own newsletters, segments and templates, and changing schedule keeps you on the tab you are on.

 | Tab | What it holds | Described under |
| --- | --- | --- |
| Newsletters | Every newsletter of the schedule, with its status, recipients, open rate and click rate | [Managing Newsletters](#managing) |
| Segments | The saved audiences a newsletter can go to, and the form that adds one | [Managing Segments](#managing-segments) |
| Templates | Designs you saved, to start a newsletter from | [Saving Your Own Templates](#saved-templates) |
| Import Emails | Addresses you already have, typed, pasted or uploaded into a segment | [Importing Emails](#importing-emails) |

 The builder lays out your email as a stack of content blocks with a live preview beside it, and audience segments decide who receives it. A newsletter never sends itself: you compose it and choose when it goes. Two other kinds of mail do leave on their own, and neither spends your allowance: the automatic new-event digest to confirmed email subscribers, and the emails people on an event's interest list signed up for. [Recipients & Segments](#recipients) sets out who is on which list.

 <a id="allowance"></a>

### Monthly Allowance

 The allowance counts **individual recipients, not newsletters**. One newsletter sent to 100 followers uses 100 of the allowance, so on the Free plan a single send reaches at most 10 people.

 | Plan | Newsletter emails per month |
| --- | --- |
| Free | 10 |
| Pro | 100 |
| Enterprise | 1,000 |
| Selfhosted | Unlimited |
| Any plan with its own email settings | Unlimited |

 Connect your own mail server in the [Email Settings](creating-schedules.md#integrations-email) row of the schedule's Integrations tab and the cap is lifted for that schedule, because the messages leave through your provider and not ours.

 While a schedule has a limit, a meter reads "5 of 100 newsletter emails sent this month" with what remains: beside the opening line of the Newsletters tab, and at the end of the title row on the Create and Edit pages. Test sends are excluded from the count. Sending is checked against the whole recipient list up front: if a send would push you past the limit it is refused outright, never delivered in part, so trim the segment or upgrade first.

  ## Newsletter Builder

 Click **Create Newsletter** to open the builder. The page has a link back to **Newsletters** above its title and the allowance meter at the end of the title row. When the schedule has saved templates, a **Start from a template** card comes first. The editing panel is split into three tabs, with a live preview pinned beside it on wide screens.

 ![The newsletter builder: the Content, Style and Settings tabs, the subject line and block list, and the live preview beside them](../../../public/images/docs/newsletters--create.png)

 ### Content

 The subject line (required) and the block list. **Add Block** opens the block palette; click a block to add it or drag it onto the canvas. Blocks are reordered by their drag handle, and each one can be selected to edit its fields inline, cloned, or removed.

 #### Style

 The [template](#templates) picker plus the colour, font and button settings described under [Style Customization](#style-customization).

 #### Settings

 Recipients (which [segments](#recipients) to send to), [A/B testing](#ab-testing) once the newsletter has been saved, and the footer text that replaces your schedule name at the bottom of the email.

 ### Buttons Along the Bottom

 | Button | What it does |
| --- | --- |
| Send a Test | Emails the current draft to your schedule's contact address |
| Save as Template | Stores the blocks and style under a name you can reuse. It stores the newsletter as it was last saved, so press Save first if you have changed something |
| Schedule | Opens a dialog to pick a future date and time to send |
| Save | Saves the draft without sending |
| Send Now | Delivers immediately to the selected recipients |

 Everything except **Save** appears only after the newsletter has been saved once, so save your draft first. After that, **Send a Test**, **Schedule** and **Send Now** each save the draft before they act, so what goes out is what is on screen.

 **Tip**

 The preview refreshes as you type and renders the same HTML your recipients receive, so use it to check block order and spacing. On phones the preview is a fourth tab that opens in a new window. A new newsletter starts from your most recent one's template, style settings and selected segments, so a house style only has to be set up once.

  ## Block Types

 Build your newsletter by combining these content blocks. A new newsletter starts with your profile image and header banner (when your schedule has them), a heading, two text blocks, an events block and your social links, all of which you can remove.

 ### Content Blocks

 #### Heading

 Large text for section titles and headlines.

 **Fields:** Heading Text, Heading Level (Header, Subheader or Section), Alignment (left, center or right)

 #### Text

 A paragraph of Markdown, written in the same editor used elsewhere in the admin panel, with a toolbar for bold, italics, lists and links.

 **Fields:** Content

 #### Events

 Pulls in your events with their flyer image, date and a link to the event page. Left on All Upcoming Events it lists up to ten upcoming or ongoing events in date order; hand-picked events are shown in the order you tick them. Either way it never includes drafts, cancelled events, unlisted events or password-protected ones.

 **Fields:** All Upcoming Events (on) or a hand-picked list of events, Event Layout (cards or list)

 #### Button

 Call-to-action button. It picks up the accent colour and corner style from the Style tab.

 **Fields:** Button Text, Button URL, Alignment

 #### Image

 One or more images, each with its own caption and link. Uploading a file requires a Pro plan   Pro   ; on the Free plan you can still point the block at an image URL you host elsewhere. Uploads accept JPG, PNG, GIF and WebP up to 10 MB.

 **Fields:** Image URL or upload, Alt text, Caption, Link, Width, Alignment, and Layout (column, row or grid) once there is more than one image

 #### Video

 Email clients cannot play video, so this block shows the YouTube thumbnail with a play badge and links out to the video. Standard watch links, youtu.be links and Shorts links are all recognised.

 **Fields:** YouTube URL

 #### Quote

 Styled blockquote with attribution, for testimonials or press pull-quotes.

 **Fields:** Quote Text, Author, Author Title

 #### Sponsors

 Your [sponsor logos](creating-schedules.md#engagement), under the section title you set on the schedule. The block shows them even while the sponsors section is switched off on your pages: a block is something you placed in this newsletter.

 **Fields:** Sponsor Source (from your schedule settings, or from the first event in the newsletter)

 #### Poll

 Shows the first active poll found across your upcoming events, with a button through to that event's page to vote. It has no fields to fill in, and renders nothing if no upcoming event has an active poll.

 ### Layout & Utility Blocks

 #### Divider

 Horizontal line to visually separate sections.

 **Fields:** Divider Style (solid, dashed or dotted)

 #### Spacer

 Adjustable vertical gap between blocks.

 **Fields:** Spacer Height, 5 to 200 pixels

 #### Social Links

 Icons linking to your profiles. The block is pre-filled from the website and social links on your schedule, and each row can be edited or removed.

 **Fields:** Platform and URL per link

 ### Auto-populated Blocks

 #### Profile Image

 Your schedule's profile image, centred like a logo. No fields: change the image on the schedule and every newsletter follows.

 #### Header Banner

 Your schedule's header image, full width at the top of the email. Also has no fields.

 **Tip**

 Leave the **Events** block on "All Upcoming Events" and a cloned newsletter always goes out with the current schedule, with no block editing between sends.

  ## Templates

 The **Style** tab opens with five built-in presets. Picking one rewrites the colour, font and button settings in a single click; your blocks, your footer text and the layout chosen on the Events block are left alone, so you can try presets on a finished draft without losing work.

 | Preset | Background | Accent | Font | Buttons |
| --- | --- | --- | --- | --- |
| Modern | White | Your schedule's accent colour | Arial | Rounded |
| Classic | Warm off-white | Brown | Georgia | Square |
| Minimal | White | Grey | Verdana | Rounded |
| Bold | Near-black navy | Red | Arial | Rounded |
| Compact | Light grey | Green | Trebuchet MS | Square |

 Every value stays editable afterwards, so a preset is a starting point rather than a lock-in. Bold is the one preset with a dark background, and Compact tightens the padding and footer type as well as the palette.

 <a id="saved-templates"></a>

### Saving Your Own Templates

 Once a design works, keep it. A saved template stores the blocks, the preset and the style settings under a name of your choosing. There are two ways to make one:

 - In the builder of a saved newsletter, click **Save as Template** and give it a name
- On the **Templates** tab of the Newsletters section, click **Create Template** to build one from scratch in the same builder
 The Templates tab shows each template as a card with the colours it was saved with, its name, its preset and the day it was made. **Use** starts a new newsletter from it, **Edit** reopens it in the builder, and **Delete** removes it after asking. Newsletters already made from a template are not affected. Whenever the schedule has at least one template, the **Start from a template** card at the top of the Create Newsletter page offers them too.

 A template has no subject line and no recipients: those belong to the newsletter you create from it, so the template builder has no Settings tab.

  ## Style Customization

 Below the preset picker, the **Style Settings** panel controls the whole email. Each colour has both a swatch picker and a hex field.

 | Option | Description |
| --- | --- |
| Background Color | The background behind the email body |
| Accent Color | Buttons, links and highlighted elements. Defaults to your schedule's accent colour |
| Text Color | Default colour for body text |
| Font Family | Arial, Georgia, Verdana, Trebuchet MS or Courier New |
| Button Style | Rounded or square corners on buttons |
| Footer Text | On the Settings tab. Replaces the schedule name printed above the unsubscribe link |

 The layout of the Events block is not a global setting: choose cards or list on the block itself.

 **Note**

 Email clients have varying CSS support, so the builder renders to table-based, inline-styled HTML with a small set of email-safe fonts. That is why the font list is short and why there is no free-form CSS here. Free schedules also carry a small "Powered by Event Schedule" line under the unsubscribe link; upgrading to Pro removes it.

   ## Email Subscribers

 Signing up asks for nothing but a name and an email address. A signed-out visitor can give your schedule an address directly, and those people become your **email subscribers**. They appear in their own list on the [Followers tab](managing-schedules.md#followers), separately from account holders who pressed Follow while signed in. Confirming an address given through the sign-up form also sets up an account for them that follows your schedule, so they are listed once, under Email subscribers, with an Account badge. They can choose a password on the confirmation page itself, or any time afterwards from the **Manage your account** link in the footer of anything you send them. An address collected at checkout is already confirmed and does not create one, unless the buyer asked for an account on the ticket form itself.

 ### Where People Sign Up

 #### The sign-up panel

 A short form on your schedule page and on each event page. Signing up here sends a confirmation email, and the address joins your audience only once that link is clicked. It is on unless you turn off **Show Sign-Up Panel** in the [Advanced](creating-schedules.md#settings-advanced) row of the schedule's Settings tab, and your sign-up link opens it either way.

 #### At checkout

 A tick box on the ticket and RSVP forms, off by default. Because the same address is already receiving a ticket, no confirmation email is needed and the person joins straight away.

 #### The Follow button

 On eventschedule.com, a signed-out visitor who presses Follow gets the same short form in a pop-up, asking for a name and an email address. It sends the same confirmation email as the sign-up panel.

 #### Your sign-up link

 The Followers tab gives you a shareable link and a QR code that open your schedule with the form ready. Useful on a poster, a flyer or a merch table.

 #### Your own website

 The same form, embedded on your website with a short snippet from the Followers tab. It sends the same confirmation email, and people who sign up there carry a Website badge. See [Embedding a Signup Form](sharing.md#embed-subscribe-form).

 ### Automatic New-Event Emails

 This is the promise made to people when they sign up, and it is kept for you. When you publish new events, your confirmed subscribers are sent a single short digest listing them.

 - One digest covering every event in the batch, never one email per event, listing up to 25 of them, soonest first
- At most one every 72 hours per schedule, however often you publish
- Only public events. Drafts, internal, unlisted and password-protected events are never announced
- Only events your schedule created. An event another schedule created and lists on yours, such as a venue's show naming you as the act, is not included
- Only events still to come. Back-filling past dates sends nothing
- Every digest carries a one-click unsubscribe link
 Turn it off per schedule with **Email subscribers about new events**, in the [Notifications](creating-schedules.md#settings-notifications) row of the schedule's Settings tab. Account followers are not included: they are reached by a newsletter you compose and send. On eventschedule.com the digest follows the same [verification rule](#verification) as newsletters, so a schedule with more than 50 confirmed subscribers needs its own email settings or a verified phone number, or those events are not announced.

 **Confirmed subscribers only**

 Someone who signed up through the panel but never clicked the confirmation link is shown as **Awaiting confirmation** and is never emailed. That is deliberate: it stops anyone signing up an address they do not own. It also means the number of subscribers on the Followers tab can be higher than the recipient count on a newsletter.

  ## Recipients & Segments

 The **Recipients** panel on the Settings tab lists your saved segments with a live recipient count beside each one. Tick as many as you need and the lists are merged. If you tick nothing, the newsletter goes to everyone: your followers and your [email subscribers](#email-subscribers) together.

 ### Three Lists, Three Kinds of Mail

 Three lists of people can hear from your schedule by email, and only two of them can be sent a newsletter:

 | List | How someone joins | What reaches them automatically | Newsletters |
| --- | --- | --- | --- |
| Email subscribers | Gives a name and email address on your schedule or event page and confirms it, or ticks the box at checkout | The new-event digest, at most one every 72 hours | Yes, through the Email subscribers segment or the default send |
| Account followers | Presses Follow while signed in on eventschedule.com, or submits an event through your curator submission form (the form says so) | Nothing | Yes, through the All Followers segment or the default send |
| An event's interest list | Leaves an email address on one event page, under "Tell me when tickets go on sale" or "Tell me if anything changes" (the “Notify me” card, where the schedule has switched it on) | That event's on-sale email, a reminder shortly before it starts, a cancellation notice, and any notice you choose to send if the date or venue changes | No. No segment reaches it, and its emails do not count against your allowance |

 The interest list belongs to one event and ends with it. See [the interest list](tickets.md#interest-list) in Selling Tickets.

 ### Segment Types

 #### All Followers

 Account holders who pressed Follow on your schedule and have not opted out of emails. Confirmed email subscribers are left out even though confirming gives them an account, so add the Email subscribers segment, or tick nothing, to reach both. Learn how to [build your follower base](sharing.md#followers).

 #### Email subscribers

 Everyone who confirmed the sign-up form, plus buyers who ticked the box at checkout. An address still awaiting confirmation is never included. See [Email Subscribers](#email-subscribers).

 #### Ticket Buyers

 Everyone who has bought a ticket or registered for one of your events, taken from the email on the order, including guests who booked an appointment and orders later cancelled or refunded. Optionally narrow it to a single event. See [Selling Tickets](tickets.md).

 #### Manual

 A list of addresses you supply, either typed in or bulk [imported](#importing-emails). Useful for a curated press or partner list.

 #### Waitlist

 People currently on a [waitlist](tickets.md#waitlist), either waiting or already notified, including the free RSVP waitlist. Available on every plan, and can be narrowed to a single event.

 #### Sub-schedule

 Ticket buyers from events filed under one sub-schedule, for category-specific promotions. The option only appears once the schedule has at least one sub-schedule.

 ### Who Actually Receives It

 - Addresses are matched case-insensitively and de-duplicated, so someone in two segments is emailed once and counts once against your allowance
- Anyone who has unsubscribed from this schedule is removed, as is anyone who has turned off emails on their account
- Every recipient is resolved at send time, so a follower who joins after you draft the newsletter is still included
 **Schedule members are always included**

 Team members on the schedule (the owner, admins and viewers) whose email is verified and who have not turned off emails always receive every newsletter, whichever segments are selected. This keeps the team in the loop on what is being sent. They count against the monthly allowance like anyone else.

  ## Managing Segments

 Segments are reusable, so build them once and pick them from the Settings tab on every future newsletter. They live on the **Segments** tab of the Newsletters section, which the **Manage Segments** link in the builder also opens. The tab lists the schedule's segments first, each with its type and its current recipient count, and the **Create Segment** form sits under the list.

 ### Creating a Segment

 1. Open the **Segments** tab
2. Under **Create Segment**, enter a name
3. Choose the type: All Followers, Email subscribers, Ticket Buyers, Manual, Waitlist or Sub-schedule (listed once the schedule has a sub-schedule)
4. Fill in whatever the type asks for next: an optional event filter for Ticket Buyers and Waitlist, the sub-schedule for Sub-schedule, or the address list for Manual (one per line, with an optional name after a comma)
5. Click **Create Segment**
 ### Editing and Deleting

 **Edit**, or the segment's name, opens the segment's own page, with a link back to **Segments** above its title. There you can rename it and see its type, its event where it has one, its recipient count and the day it was made. The type and the event or sub-schedule are chosen when the segment is created and have no field on this page: to change either, create a new segment.

 Under that, the page lists the people in the segment. On a manual segment you can add one by name and email under **Add Subscriber**, correct an entry in place, or delete it, and **Import emails in bulk** opens the Import Emails tab. Every other type is worked out afresh each time, so its list is read-only and shows the first 50 recipients.

 **Delete** asks first. A segment that is still attached to a draft or scheduled newsletter cannot be deleted: detach it there first, then delete.

  ## Importing Emails

 Bring an existing mailing list across on the **Import Emails** tab of the Newsletters section. Up to 10,000 addresses can be imported at a time, on every plan.

 ### Choosing a Segment

 The first card, **Select segment**, decides where the contacts land: **Create new segment** with a name you provide, or **Add to existing segment**. Imports always go into a manual segment, so only manual segments appear in that dropdown, and the second choice is offered only once the schedule has one.

 ### Import Methods

 The second card has a tab for each way in. **Paste Emails** and **Upload CSV** both end by filling the **Form Entry** rows, where you can still correct them, and **Confirm Import** there is what saves.

 #### Form Entry

 Type contacts in row by row with name and email. The email is required, the name is optional. Best for small lists or one-off additions.

 #### Paste Emails

 Paste a block of text and click **Parse Emails**. One address per line, `Name <email>`, comma-separated addresses and "email, name" pairs are all understood.

 #### Upload CSV

 Drop in a CSV of up to 10 MB, then map each column to Email, Name or Skip. The importer guesses the mapping and shows a preview with the row count before anything is saved. **Next** carries the rows to Form Entry.

 Whichever method you use, the rows are validated first and any bad or duplicate address is reported by row number so you can fix it before confirming. Addresses already in the target segment are skipped, not duplicated. After a successful import you land on the Segments tab, with a message saying how many addresses were added.

 **Only import people who agreed to hear from you**

 Importing adds each address to the segment and nothing else: no account is created for anyone, and nobody is added to your followers. You are responsible for having their permission to email them, and every recipient counts against your monthly allowance.

  ## Sending

 You have three ways to deliver a saved newsletter:

 ### Send Now

 Confirm the prompt and the newsletter moves to Sending. Messages go out in small batches a few seconds apart rather than all at once, so a large send takes a few minutes to finish.

 #### Schedule

 Pick a future date and time, read in your own timezone. The send fires within a minute of that time. A notice at the top of the edit page shows the scheduled time and offers **Cancel Schedule**, which returns it to Draft.

 #### Send a Test

 Sends one copy to your schedule's contact email address, so set that address first. Test sends do not count against your monthly allowance and are excluded from the statistics, and there is a short cooling-off period between tests. Send one before every real send: images, links and dark-mode rendering are the three things that most often look different in a real inbox from the builder preview.

 <a id="verification"></a>

### Verification Above 50 Recipients

 On eventschedule.com, a newsletter going to more than 50 recipients needs either [your own email settings](creating-schedules.md#integrations-email) on the schedule or a [verified phone number](account-settings.md#profile) in your profile. Up to 50 recipients it goes out without either, and so does a test. Until you have one, a notice on the Newsletters tab and in the builder says so and links to both, and a larger send, whether now or scheduled, is refused. The automatic new-event digest follows the same rule. Selfhosted installs are not affected.

 **Selfhosted: give the queue a real driver**

 With the `sync` queue driver an immediate send of more than 50 recipients is refused, because the whole delivery would have to happen inside one web request. Either schedule the newsletter instead or configure a background queue driver and a worker. See the [selfhost installation guide](/docs/selfhost/installation).

  ## A/B Testing

 An A/B test pairs two versions of one newsletter, variant A and variant B, so you can compare how each one did. Open the **A/B Testing** panel on the Settings tab of a saved newsletter.

 ### Setting Up a Test

 1. Click **Create A/B Test** to open the form
2. Under **Field to Test**, choose what the two versions will differ in: **Subject** or **Content Above Events**
3. Set **Sample Size (%)**, from 5 to 50 (20 by default), and **Wait Hours**, from 1 to 72 (4 by default)
4. Under **Winner Criteria**, choose **Open Rate** or **Click Rate**
5. Click **Create A/B Test** under the form. Your newsletter becomes variant A and a copy is created as variant B, which opens for editing so you can make the change you want to test
 From then on each variant says which one it is in its A/B Testing panel, and carries an A or B chip beside its subject on the Newsletters tab. A newsletter can belong to one test only, and a test always has exactly two variants.

 <a id="ab-sending"></a>

### Sending the Variants

 The builder has no button that sends a sample. **Send Now** and **Schedule** work on a variant as they do on any newsletter: they send that one variant to everyone its recipients resolve to. The sample size, the wait and the winner criteria are saved with the test, but nothing in the builder acts on them, so no sample is drawn and no winner is picked or sent on.

 **Do not send both variants to the same audience**

 Variant B starts with the same recipients as variant A. Send both without changing them and every person receives two emails, and both sends count against your [monthly allowance](#allowance). To compare two versions, give each variant its own segment on its Settings tab, for example two manual segments holding half of a list each, then send both.

 ### Comparing the Results

 Once a variant is sending or sent, its [statistics](#analytics) page carries an **A/B Test Results** card with both variants side by side: the subject, the sent count, the open rate and the click rate of each. The comparison needs enough recipients to mean anything: on a list of a few dozen people the difference between variants is usually noise.

  ## Analytics

 Once a newsletter is sending or sent, its subject on the Newsletters tab, and the **Stats** link at the end of its row, open its statistics page. The page is titled with the newsletter's subject, says when it was sent, and has a link back to **Newsletters** above the title. The list itself also carries sortable Recipients, Open Rate and Click Rate columns.

 | Metric | Description |
| --- | --- |
| Sent | How many messages were delivered to the mail server |
| Opens | Recipients who opened the email, and that as a percentage of sent |
| Clicks | Recipients who clicked at least one link, and that as a percentage of sent |
| Failed | Messages the mail server rejected or could not deliver |
| Opens Over Time, Clicks Over Time | Two charts plotting activity by day since the send |
| Top Clicked Links | The ten most-clicked destinations, with their click counts |
| Recipients | A sortable list of every recipient, 50 to a page, with their name, email address, status, and when they opened and clicked |

 Opens are measured with a tracking pixel, so a recipient whose client blocks remote images counts as unopened even if they read the email. Treat the open rate as a floor and a trend rather than an exact number. Clicks are counted by routing each link through a redirect, which is also why link addresses in the delivered email look different from the ones you typed.

 **Newsletter traffic shows up in schedule analytics too**

 Links are tagged as newsletter traffic on their way out, so visits arriving from a send appear under traffic sources in [Analytics](analytics.md#web-analytics). That is how you tell what a newsletter did after the click, not just up to it.

  ## Managing Newsletters

 The **Newsletters** tab lists every newsletter of the selected schedule, 20 to a page: its subject, its status with the time it was or will be sent, and once it has gone out its recipients, open rate and click rate. Click a column heading to sort by it. The subject opens the builder for a draft or a scheduled newsletter and the statistics for one that is sending or sent, and the links at the end of the row are **Edit** or **Stats**, **Clone** and **Delete**. With more than one schedule, the picker at the end of the title row switches between them.

 ### Draft

 In progress, not sent or scheduled. Editable at any time.

 #### Scheduled

 Queued for a future time. Still editable, and **Cancel Schedule** on the edit page puts it back to Draft.

 #### Sending

 Delivery is under way. It cannot be edited or deleted while in this state, and statistics are already available.

 #### Sent

 Delivered. Open its statistics, or clone it to start the next one.

 ### Cloning

 **Clone** copies the blocks, style and recipients into a fresh draft and opens it, leaving the original untouched. Statistics and any A/B test are not carried over. This is the quickest way to run a recurring digest.

 ### Deleting

 **Delete** asks first, then removes the newsletter from the list. A newsletter that is currently sending has no Delete link; wait for it to finish.

 ### Unsubscribes

 Every newsletter footer carries an unsubscribe link. One click opts that address out and it is excluded from every future send for that schedule, automatically and with no work from you. Unsubscribes are per schedule, so someone who leaves one of your schedules still hears from the others. Recipients can also turn off all email from their own account settings, which removes them everywhere.

  ## See Also

 - [Sharing Your Schedule](sharing.md) - Grow the follower list your newsletters go to
- [Event Graphics](event-graphics.md) - Generate shareable images for social media
- [Creating Events](creating-events.md) - Add events that appear in your newsletters
- [Analytics](analytics.md) - Track how newsletters drive schedule views
- [Selling Tickets](tickets.md) - Set up ticketing, waitlists and buyer lists
