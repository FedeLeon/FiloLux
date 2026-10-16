<?php if(user() && hasLiveNotifications()): $liveCursor=(int)query('SELECT COALESCE(MAX(id),0) FROM live_notifications')->fetchColumn(); ?>
<div class="live-toasts" data-live-notifications data-cursor="<?= $liveCursor ?>" data-user="<?= (int)user()['id'] ?>" data-role="<?= e(user()['role']) ?>" aria-live="polite" aria-relevant="additions" aria-label="Notificaciones"></div>
<?php endif ?>
