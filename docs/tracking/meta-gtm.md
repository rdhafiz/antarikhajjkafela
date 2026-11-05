# Meta Pixel in Google Tag Manager

The website does not call `fbq` itself (Settings → Tracking → Facebook → "Meta Pixel
loaded by" = **Google Tag Manager**, the default). It pushes the events below to the
`dataLayer`, and the tags in this guide send them to Meta.

Checkout and payment events (`InitiateCheckout`, `Purchase`) are sent by the server
through the Conversions API. Do **not** add them in Tag Manager. They need the Pixel ID
and the Conversions API token in Settings.

| Meta event | dataLayer event | When |
| --- | --- | --- |
| PageView | (All Pages) | every page |
| ViewContent | `meta_view_content` | package pages, service pages, Air Tickets and Visa pages |
| Contact | `meta_contact` | click on a phone, WhatsApp, Messenger or email link |
| Lead | `meta_lead` | enquiry saved (air quote, visa, contact form) or package booking sent |
| BookingStatusCheck (custom) | `meta_booking_status_check` | "Find your booking" form sent |

Before each event the site pushes `{ meta: null }`, so values of an earlier event never
leak into the next one.

## A. Variables

Variables → User-Defined Variables → New.

| Name | Type | Value |
| --- | --- | --- |
| `Const - Meta Pixel ID` | Constant | your Pixel ID |
| `DLV - meta.content_type` | Data Layer Variable (v2) | `meta.content_type` |
| `DLV - meta.content_ids` | Data Layer Variable (v2) | `meta.content_ids` |
| `DLV - meta.content_name` | Data Layer Variable (v2) | `meta.content_name` |
| `DLV - meta.content_category` | Data Layer Variable (v2) | `meta.content_category` |
| `DLV - meta.value` | Data Layer Variable (v2) | `meta.value` |
| `DLV - meta.currency` | Data Layer Variable (v2) | `meta.currency` |
| `DLV - meta_extra_pixel_id` | Data Layer Variable (v2) | `meta_extra_pixel_id` |

`meta_extra_pixel_id` is the "Extra Facebook Pixel ID" a campaign page can set.

## B. Triggers

Triggers → New → **Custom Event**, event name exactly as below, "All Custom Events".

| Name | Event name |
| --- | --- |
| `CE - meta_view_content` | `meta_view_content` |
| `CE - meta_contact` | `meta_contact` |
| `CE - meta_lead` | `meta_lead` |
| `CE - meta_booking_status_check` | `meta_booking_status_check` |

## C. Tags

All tags are **Custom HTML**. Write the variables without quotes: Tag Manager puts the
real value in (text, number or list). Empty values are left out by the Pixel.

### C1. Meta Pixel - Base code + PageView

Trigger: **All Pages**. Advanced Settings → Tag firing options: **Once per page**.

```html
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');

fbq('init', {{Const - Meta Pixel ID}});
var extraPixel = {{DLV - meta_extra_pixel_id}};
if (extraPixel) fbq('init', String(extraPixel));
fbq('track', 'PageView');
</script>
```

For each tag below: Advanced Settings → **Tag Sequencing** → "Fire a tag before" →
`Meta Pixel - Base code + PageView`.

### C2. ViewContent

Trigger: `CE - meta_view_content`

```html
<script>
fbq('track', 'ViewContent', {
    content_type: {{DLV - meta.content_type}},
    content_ids: {{DLV - meta.content_ids}},
    content_name: {{DLV - meta.content_name}},
    content_category: {{DLV - meta.content_category}},
    value: {{DLV - meta.value}},
    currency: {{DLV - meta.currency}}
});
</script>
```

Packages send `content_type: 'product'`, the package id and name, the service name as
category and, when the price is shown, `value` (lowest price) and `currency` (BDT).
Services and the Air Tickets / Visa pages send `content_type: 'product_group'` and
`content_category: 'service'`.

### C3. Contact

Trigger: `CE - meta_contact`

```html
<script>
fbq('track', 'Contact', {
    content_category: {{DLV - meta.content_category}}
});
</script>
```

`content_category` is `phone`, `whatsapp`, `messenger` or `email`.

### C4. Lead

Trigger: `CE - meta_lead`

```html
<script>
fbq('track', 'Lead', {
    content_category: {{DLV - meta.content_category}},
    content_type: {{DLV - meta.content_type}},
    content_ids: {{DLV - meta.content_ids}},
    content_name: {{DLV - meta.content_name}}
});
</script>
```

`content_category` is the enquiry type: `fare_quote` (air tickets), `visa_enquiry`,
`contact_form`, `campaign_form` (campaign pages) or `lead_form` (other pages), or
`package_booking` for a booking, which also sends the package's `content_type`,
`content_ids` and `content_name`. The event is pushed only after the server has saved the
enquiry or booking (spam is never reported). After a booking the site waits for the tag
(up to 1 second) before opening the booking page.

### C5. BookingStatusCheck (custom)

Trigger: `CE - meta_booking_status_check`

```html
<script>
fbq('trackCustom', 'BookingStatusCheck');
</script>
```

## D. Check and publish

1. Settings → Tracking: Tag Manager ID set, "Meta Pixel loaded by" = Google Tag Manager,
   Pixel ID and Conversions API token set.
2. Remove any other Meta Pixel tag (for example a template tag) from the container, or
   events count twice.
3. Tag Manager **Preview** on the live site: open a package, tap the phone number, send
   an enquiry and check each tag fires once. Meta Events Manager → Test events shows them.
4. **Submit** the container.
