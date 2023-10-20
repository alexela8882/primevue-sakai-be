## Installation

1. `git clone https://github.com/alexela8882/primevue-sakai-be.git <project_name>`
2. `cd <project_name>`
3. `git checkout <your_designated_branch>`
4. `composer install`

## Project Setup
1. `cd <project_name>`
2. Make sure you already have mysql or mongodb setup and your `.env` file configuration is ready.
3. `php artisan migrate`
4. `php artisan db:seed`
5. `php artisan passport:install`
6. `php artisan serve` or setup linux nginx/apache.