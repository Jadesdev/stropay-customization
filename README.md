<<<<<<<< Update Guide >>>>>>>>>>>

Immediate Older Version: 3.1.0
Current Version: 3.2.0

Feature Update:
1. Strowallet Api in admin panel
2. Strowallet System added for user web
3. Added Card Limit Option In Admin Panel(Virtual Card Api)
4. Added Card Limit Option All Virtual Card Web & Api
5. Convert Single Card Creation To Multiple Card(Flutterwave)
6. Fixed Send Remittance Response Type
7. Make Default System For All Virtual Cards (Web & APi)
8. Added Tatum Payment Gateway
9. Update RazorPay Payment Gateway
10. Translate All Text By Language




Please Use This Commands On Your Terminal To Update Full System
1. To Run project Please Run This Command On Your Terminal
    composer update && composer dumpautoload && php artisan migrate:fresh --seed && php artisan passport::install --force
