-- php artisan migrate --pretend output for:
--   $table->string('status', 100)->default('pending')->change();
--   $table->index('customer_email');
--   $table->foreignId('user_id')->constrained()->change();   (adds the foreign key)
alter table `orders` modify `status` varchar(100) not null default 'pending';
alter table `orders` add index `orders_customer_email_index`(`customer_email`);
alter table `orders` add constraint `orders_user_id_foreign` foreign key (`user_id`) references `users` (`id`);
