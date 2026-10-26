# Image credits

## Page backgrounds (Unsplash)

These are seeded by `HeroImagesSeeder` from `database/seeders/images/heroes/`. Each photo was checked on its Unsplash page as "Free to use under the Unsplash License" (https://unsplash.com/license). That license allows commercial use without permission or attribution; the credits below are kept as a courtesy and a record of where each image came from.

| File | Used on | Photographer | Source |
| --- | --- | --- | --- |
| home-haram.webp | Home slide "Hajj & Umrah"; admin Menus header (cropped copy in `resources/admin/assets/menus-header.webp`) | Ömer F. Arslan | https://unsplash.com/photos/W0FhhtnMd8k |
| hajj-campaign.webp | Hajj pre-registration campaign; admin Campaign pages header (cropped copy in `resources/admin/assets/campaigns-header.webp`) | ibrahim uz | https://unsplash.com/photos/3Z3RvzpVAqM |
| hajj-crowd.webp | Hajj service, other Hajj packages; admin Media library header (cropped copy in `resources/admin/assets/media-header.webp`) | ekrem osmanoglu | https://unsplash.com/photos/0bdDM8n8wYw |
| hajj-premium.webp | Hajj Premium Package; admin Roles header (cropped copy in `resources/admin/assets/roles-header.webp`) | Haidan | https://unsplash.com/photos/8Kg-2kK97Xc |
| hajj-regular.webp | Hajj Regular Package; booking form step 4 (cropped copy in `public/images/booking/haram-dusk.webp`); admin Users header (cropped copy in `resources/admin/assets/users-header.webp`) | tasnim umar | https://unsplash.com/photos/blbwkwHPItE |
| haram-wide.webp | Default page banner; Umrah fallback; admin Packages header (cropped copy in `resources/admin/assets/packages-header.webp`) | Jubair Hossain | https://unsplash.com/photos/WuIroq-Q1hY |
| madinah-golden.webp | About (Hajj & Umrah division section); admin Inquiries and Profile headers (cropped copies in `resources/admin/assets/inquiries-header.webp` and `profile-header.webp`) | Sulthan Auliya | https://unsplash.com/photos/rzzS0_pMsD0 |
| makkah-hotels.webp | Post "Important information for Hajj pilgrims"; admin Services header (cropped copy in `resources/admin/assets/services-header.webp`) | Sikiru Salami | https://unsplash.com/photos/jZp68sRx2w4 |
| madinah-umbrellas.webp | Booking form step 2 (cropped, mirrored copy in `public/images/booking/madinah-scene.webp`); admin Bookings header (cropped copy in `resources/admin/assets/bookings-header.webp`) | Haidan | https://unsplash.com/photos/RhkbHU14MoA |
| haram-courtyard.webp | Gallery; booking form step 1 (cropped copy in `public/images/booking/haram-scene.webp`); admin Gallery header (cropped copy in `resources/admin/assets/gallery-header.webp`) | Sam Riz | https://unsplash.com/photos/ceNCWYqL8DY |
| green-dome.webp | Reviews; admin Blog and Settings headers (cropped copies in `resources/admin/assets/blog-header.webp` and `settings-header.webp`); booking form step 3 (cropped, mirrored copy in `public/images/booking/dome-scene.webp`) | A Miah | https://unsplash.com/photos/hJXiPheKFus |
| quran.webp | Blog & news; admin Reviews header (cropped copy in `resources/admin/assets/reviews-header.webp`) | Abdullah Arif | https://unsplash.com/photos/Dxi6KbpvUgA |
| nabawi-colonnade.webp | Admin Pages and Trash headers (cropped copies in `resources/admin/assets/pages-header.webp` and `trash-header.webp`) | djonk creative | https://unsplash.com/photos/XqO6H6ikj1w |

## Social share images

`SeoSeeder` crops the same Unsplash photos to 1200×630 JPEG (media usage `og`) as the share image of each page, service, package and post, and sets the home photo as the default share image in the SEO settings:

| Photo | Share image for |
| --- | --- |
| travel/travellers.webp | Home, About, Tours, and the default for pages without their own |
| madinah-golden.webp | Post "Umrah 2025: November group" |
| travel/airport.webp | Services, Contact, Terms & conditions, Privacy policy |
| travel/airplane-sky.webp, travel/passport-visa.webp | Air Tickets, Visa |
| makkah-hotels.webp | Post "Important information for Hajj pilgrims" |
| haram-courtyard.webp | Gallery; booking form step 1 (cropped copy in `public/images/booking/haram-scene.webp`); admin Gallery header (cropped copy in `resources/admin/assets/gallery-header.webp`) |
| quran.webp | Blog & news |
| green-dome.webp | Reviews; Umrah Package; admin Blog and Settings headers (cropped copies in `resources/admin/assets/blog-header.webp` and `settings-header.webp`); booking form step 3 (cropped, mirrored copy in `public/images/booking/dome-scene.webp`) |
| hajj-campaign.webp | Hajj pre-registration campaign; admin Campaign pages header (cropped copy in `resources/admin/assets/campaigns-header.webp`) |
| hajj-crowd.webp | Hajj |
| haram-wide.webp | Umrah; post "What is Umrah?" |
| hajj-regular.webp | Hajj Regular Package; post "How to perform Umrah" |
| hajj-premium.webp | Hajj Premium Package; admin Roles header (cropped copy in `resources/admin/assets/roles-header.webp`) |

Gallery albums share their own cover photo.

## Travel photos (`database/seeders/images/travel`)

Unsplash photos (free to use under the Unsplash License) for the travel agency pages, imported by `TourSeeder`, `HeroImagesSeeder` and `SeoSeeder` (each tour also uses its photo as its share image):

| File | Used for | Photographer | Source |
| --- | --- | --- | --- |
| panorama.webp | Home hero, first slide | Supplied by the client (not from Unsplash) | — |
| airplane-sky.webp | Air Tickets banner; home call-to-action and default page banner; booking step 1 for tours (cropped copy in `public/images/booking/sky-scene.webp`) | Marco Tjokro | https://unsplash.com/photos/cvj8Li-amfw |
| airport.webp | Home slide "Air tickets"; Services, Terms and Privacy banners; booking step 2 for tours (`airport-scene.webp`) | Albert Stoynov | https://unsplash.com/photos/Yil5cKBbS5M |
| passport-visa.webp | Home slide "Visa"; Visa banner and documents section; booking step 3 for tours (`passport-scene.webp`) | Global Residence Index | https://unsplash.com/photos/addEU6TScGI |
| travellers.webp | Home hero; Tours service; default share image | Westwind Air Service | https://unsplash.com/photos/yRp1lQSnbk0 |
| maldives.webp | Home slide "Tours"; Maldives tour; "Tour packages" service card; booking step 4 for tours (`island-scene.webp`) | Matheen Faiz | https://unsplash.com/photos/iwZqmickJxA |
| thailand.webp | Thailand tour | Evan Krause | https://unsplash.com/photos/BU6lABNbTpA |
| malaysia.webp | Malaysia tour | Vlad Shapochnikov | https://unsplash.com/photos/uPJzWWhPcP4 |
| singapore.webp | Singapore tour | Hu Chen | https://unsplash.com/photos/__cBlRzLSTg |
| dubai.webp | Dubai & Abu Dhabi tour | David Rodrigo | https://unsplash.com/photos/Fr6zexbmjmc |
| nepal.webp | Nepal tour | Sebastian Pena Lambarri | https://unsplash.com/photos/Wj9ELwGXa6c |
| kashmir.webp | Kashmir tour | Naweedey | https://unsplash.com/photos/XHG0uFAlEGM |
| bhutan.webp | Bhutan tour | Mohamed Abdul Rasheed | https://unsplash.com/photos/fwP7lCNdY2M |
| istanbul.webp | Türkiye tour | Paul Bill | https://unsplash.com/photos/cDE_FDdPT6Y |
| egypt.webp | Egypt tour | Jeremy Bishop | https://unsplash.com/photos/HcgK4WoBwzg |
| europe.webp | Europe tour | Anthony Delanoix | https://unsplash.com/photos/QAwciFlS1g4 |
| japan.webp | Japan tour | Aditya Anjagi | https://unsplash.com/photos/KZSDCocsOEE |
| al-aqsa.webp | Al-Aqsa & Jordan tour | Thales Botelho de Sousa | https://unsplash.com/photos/oJMwapMuApk |
| uzbekistan.webp | Uzbekistan tour | LKHTK | https://unsplash.com/photos/pQMkpG36644 |
| sydney.webp | Visa card "North America & Oceania" | April Pethybridge | https://unsplash.com/photos/KeDdztmEqVc |

## From the previous website

See "Images used for seeding" in `old-site-content.md`.
