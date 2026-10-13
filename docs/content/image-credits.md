# Image credits

## Page backgrounds (Unsplash)

These are seeded by `HeroImagesSeeder` from `database/seeders/images/heroes/`. Each photo was checked on its Unsplash page as "Free to use under the Unsplash License" (https://unsplash.com/license). That license allows commercial use without permission or attribution; the credits below are kept as a courtesy and a record of where each image came from.

| File | Used on | Photographer | Source |
| --- | --- | --- | --- |
| home-haram.webp | Home hero | Ömer F. Arslan | https://unsplash.com/photos/W0FhhtnMd8k |
| hajj-campaign.webp | Hajj pre-registration campaign | ibrahim uz | https://unsplash.com/photos/3Z3RvzpVAqM |
| hajj-crowd.webp | Hajj service, other Hajj packages | ekrem osmanoglu | https://unsplash.com/photos/0bdDM8n8wYw |
| hajj-premium.webp | Hajj Premium Package | Haidan | https://unsplash.com/photos/8Kg-2kK97Xc |
| hajj-regular.webp | Hajj Regular Package | tasnim umar | https://unsplash.com/photos/blbwkwHPItE |
| haram-wide.webp | Default page banner; Umrah fallback; admin Packages header (cropped copy in `resources/admin/assets/packages-header.webp`) | Jubair Hossain | https://unsplash.com/photos/WuIroq-Q1hY |
| madinah-golden.webp | About; admin Inquiries header (cropped copy in `resources/admin/assets/inquiries-header.webp`) | Sulthan Auliya | https://unsplash.com/photos/rzzS0_pMsD0 |
| makkah-hotels.webp | Services; admin Services header (cropped copy in `resources/admin/assets/services-header.webp`) | Sikiru Salami | https://unsplash.com/photos/jZp68sRx2w4 |
| madinah-umbrellas.webp | Contact; admin Bookings header (cropped copy in `resources/admin/assets/bookings-header.webp`) | Haidan | https://unsplash.com/photos/RhkbHU14MoA |
| haram-courtyard.webp | Gallery | Sam Riz | https://unsplash.com/photos/ceNCWYqL8DY |
| green-dome.webp | Reviews | A Miah | https://unsplash.com/photos/hJXiPheKFus |
| quran.webp | Blog & news | Abdullah Arif | https://unsplash.com/photos/Dxi6KbpvUgA |
| nabawi-colonnade.webp | Terms & conditions, Privacy policy | djonk creative | https://unsplash.com/photos/XqO6H6ikj1w |

## Social share images

`SeoSeeder` crops the same Unsplash photos to 1200×630 JPEG (media usage `og`) as the share image of each page, service, package and post, and sets the home photo as the default share image in the SEO settings:

| Photo | Share image for |
| --- | --- |
| home-haram.webp | Home, and the default for pages without their own |
| madinah-golden.webp | About; post "Umrah 2025: November group" |
| makkah-hotels.webp | Services; post "Important information for Hajj pilgrims" |
| madinah-umbrellas.webp | Contact |
| haram-courtyard.webp | Gallery |
| quran.webp | Blog & news |
| green-dome.webp | Reviews; Umrah Package |
| nabawi-colonnade.webp | Terms & conditions, Privacy policy |
| hajj-campaign.webp | Hajj pre-registration campaign |
| hajj-crowd.webp | Hajj |
| haram-wide.webp | Umrah; post "What is Umrah?" |
| hajj-regular.webp | Hajj Regular Package; post "How to perform Umrah" |
| hajj-premium.webp | Hajj Premium Package |

Gallery albums share their own cover photo.

## From the previous website

See "Images used for seeding" in `old-site-content.md`.
