# Referral Program

Earn free months by referring other organizers.

## Overview

 The referral program lets you earn credit towards Event Schedule by inviting other event organizers to the platform. When someone signs up through your referral link and then pays for a Pro or Enterprise plan, you earn a credit worth one month of the plan they are on. You choose which of your schedules the credit lands on.

 Open it from **Referrals** in the admin panel sidebar; the page that opens is titled **Referral Program**. The **Plan** tab of any schedule also carries a **View Referral Dashboard** link to the same page.

 From top to bottom the page holds [your referral link](#referral-link), a [strip of four figures](#dashboard), any [credits ready to apply](#applying-credits), a **How It Works** card, and your [referral history](#history).

 **Who can take part**

 Every account on eventschedule.com can refer, including accounts on the Free plan, and there is no cap on how many people you refer. The program runs on eventschedule.com only: a [selfhosted](/docs/selfhost) install has no subscriptions to refer anyone to, so the Referrals item does not appear in its sidebar.

  ## How It Works

 The **How It Works** card on the Referrals page sums this up in three steps, **Share Your Link**, **They Subscribe** and **Earn Your Credit**, with the value of each credit underneath. In full, a referral moves through five stages:

 1. **You share your link.** Copy it from the top of the Referrals page and send it to another organizer.
2. **They create an account.** The referral is recorded the moment they sign up, with the status **Pending**. Signing up with an email address and signing up with Google both count.
3. **They subscribe.** When they start a paid Pro or Enterprise subscription on one of their schedules, the referral moves to **Subscribed** and the 30-day clock starts.
4. **They stay 30 days.** Thirty days after they subscribed, if the subscription is still active or in its cancellation grace period, the referral becomes **Qualified** and the credit is yours. You get an email, and a push notification if you have those switched on.
5. **You apply the credit.** Pick a schedule to spend it on and the referral is marked **Credited**.
 ### What counts as a referral

 - The visitor has to reach Event Schedule through your link and sign up in the same browsing session. The code is held only for that session, not in a long-lived cookie, so a visit today and a signup next week will not be linked.
- The code travels from the page they land on to the sign-up form in a browser cookie, and that cookie is written only once the visitor has allowed marketing cookies in the cookie notice. If they decline, or never answer it, the code normally does not reach sign-up and no referral is recorded.
- The first referral link counts. If they followed another organizer's link earlier in the same session, that organizer gets the referral, not you.
- Each person can be referred once. If someone already has an Event Schedule account, or was already referred by another organizer, a new referral is not created for them.
- You cannot refer yourself. A signup that matches your own account is ignored.
- The 30-day clock starts when the subscription starts, and the 7-day free trial counts towards it.
- Statuses are recalculated once a day, so a referral that hits its 30th day shows as Qualified on the next daily run rather than to the minute.
 **One credit per person referred**

 A referral produces at most one credit, no matter how many schedules the person you referred goes on to run or how long they stay. It also has to still be Pending when they subscribe: if it has already expired (see [Referral Statuses](#statuses)), a later subscription will not revive it.

  ## Your Referral Link

 The **Your Referral Link** card sits at the top of the Referrals page. Click **Copy Link** and the button confirms with **Copied!**, then paste the link into an email, a post or a message. If your browser will not let the page copy for you, the link is selected instead, ready to copy by hand.

 ![The Your Referral Link card, with the link and its Copy Link button](../../../public/images/docs/referral-link.png)

 The link is the Event Schedule home page with an eight-character code on the end, in the form `/?ref=a1b2c3d4`. The code is created the first time you open the Referrals page and never changes after that.

 You are not limited to the home page. Adding `?ref=yourcode` to any Event Schedule page works the same way, so you can point people straight at [Pricing](https://eventwillow.com/pricing) or at a feature page and still get the credit.

 **Tip**

 Share your referral link on social media, in event communities, or directly with organizers you know. Because attribution only lasts for the visit, links that lead somewhere worth reading straight away, such as the pricing page, convert better than a link someone bookmarks for later.

  ## Referral Dashboard

 A strip of four figures sits under the referral link, one per stage of the funnel:

 ![The four referral figures: Total Referrals, Awaiting Subscription, Awaiting Qualification and Credits Earned](../../../public/images/docs/referral-dashboard.png)

 | Figure | What it counts |
| --- | --- |
| Total Referrals | Everyone who has signed up through your link, at any status |
| Awaiting Subscription | Referrals still at Pending: the account exists but has not paid for a plan yet |
| Awaiting Qualification | Referrals at Subscribed: they are paying and part way through the 30 days |
| Credits Earned | Credits you have already applied to a schedule. Credits that are earned but not yet spent are not counted here: they are listed just below, in Credits Ready to Apply |

  ## Rewards

 A credit is worth one month of the plan the person you referred is on, at the standard monthly price:

 | Their plan when the referral qualifies | Credit you earn |
| --- | --- |
| Pro | $9, one month of Pro |
| Enterprise | $24, one month of Enterprise |

 **The tier is read on day 30, not on day 1**

 The credit follows whichever plan their subscription is on at the moment it qualifies. Someone who starts on Pro and moves up to Enterprise inside the first 30 days earns you the $24 Enterprise credit, and the history table updates to match. The amounts are fixed at $9 and $24 whether they pay monthly or yearly.

  ## Applying Credits

 Once a referral qualifies, it appears in the **Credits Ready to Apply** card on the Referrals page, between the four figures and How It Works. The card is only there while you have at least one unspent credit, and it lists one row per credit.

 ![The Credits Ready to Apply card, with a schedule picker and an Apply Credit button on each credit](../../../public/images/docs/referral-credits.png)

 1. Open **Referrals** in the admin panel sidebar
2. Find the credit in **Credits Ready to Apply**. Each row shows the value followed by the plan, such as $9 credit with a Pro chip beside it
3. Choose a schedule from the **Select schedule** dropdown on that row. Only schedules you own are listed, not ones where you were added as a team member
4. Click **Apply Credit**. The page comes back with **Referral credit applied successfully.** at the top, and the referral moves to Credited in the history
 **Applying a credit cannot be undone**

 There is no confirmation step: the credit is spent on the schedule you chose as soon as you click Apply Credit. It cannot be split across schedules or moved afterwards, so check the dropdown first.

 ### What applying a credit does

 What happens next depends on whether the schedule you picked is already paying:

 | Schedule you apply it to | What the credit does |
| --- | --- |
| Has an active subscription | The $9 or $24 goes on the schedule's billing balance and comes off its next invoice automatically. Your plan and renewal date are untouched, and a credit larger than the invoice carries over to the one after |
| Has no active subscription | The schedule is moved onto the credit's tier for 30 days. If it already had time left on a plan, the 30 days are added to the end of it rather than replacing it |

 To try a paid tier on a schedule that is not paying yet, spend the credit there: that is the case where it buys 30 days of the plan outright rather than $9 or $24 off a bill.

 A month of Pro or Enterprise you earned through a referral counts as a plan you earned, not one handed to you. It takes the Event Schedule credit chip off your public pages like a plan you pay for, rather than keeping it the way an admin-granted plan does.

  ## Referral Statuses

 Every referral carries one of five statuses, shown in the **Status** column of the history table as a coloured dot beside the word: grey for Pending, blue for Subscribed, amber for Qualified, green for Credited and red for Expired.

 | Status | Meaning |
| --- | --- |
| Pending | They have created an account through your link but have not paid for a plan yet. The Plan Tier column stays empty while a referral is at this stage |
| Subscribed | They have started a paid Pro or Enterprise subscription and the 30-day qualifying period is running |
| Qualified | They were still subscribed 30 days later, so the credit is yours and is waiting in Credits Ready to Apply |
| Credited | You have spent the credit, and the schedule it went to is named in the Credited To column |
| Expired | The referral can no longer earn anything. This happens two ways: their subscription ended before the 30 days were up, or they never subscribed at all and 90 days have passed since they signed up. Cancelling alone does not expire it: a cancelled subscription still counts until its paid time runs out |

 **Statuses move once a day**

 A daily job promotes referrals to Qualified and expires the ones that have run out of time, so a status can be up to a day behind what happened on the account. A credit that has already reached Qualified stays yours to spend even if the person you referred cancels later.

  ## Referral History

 The **Referral History** card at the bottom of the page lists every referral you have made, one row each. It appears once you have at least one. On a phone each referral is a stacked block with the same details and no column headings, so sorting needs a wider screen.

 ![The Referral History list, with a masked email address, date, plan and status for each referral](../../../public/images/docs/referral-history.png)

 | Column | Description |
| --- | --- |
| Referred User | Their email address, partly masked, in the form `li***@example.com`. You never see a referred organizer's full address or name |
| Date | When they signed up through your link. Click the heading to sort; the list starts newest first |
| Plan Tier | A Pro or Enterprise chip, shown once they subscribe. The cell is empty while the referral is still Pending, or if it expired without a subscription |
| Status | The current status (see [Referral Statuses](#statuses) above). This heading is sortable too |
| Credited To | The schedule you applied the credit to. Empty until a credit has been applied |

 The list shows 20 referrals per page, with paging links underneath once you pass that.
