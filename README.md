<!-- Run following commands to run project -->

Setup .env

```bash
copy .env.example .env
php artisan key:generate
```

<!-- Place these settings in .env file -->
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dental_project
DB_USERNAME=root
DB_PASSWORD=

<!-- run following commands -->

php artisan migrate

php artisan db:seed

php artisan serve

<!-- Login Credentials -->
admin@dental.com
password: password123