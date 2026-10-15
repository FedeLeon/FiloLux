<?php // Decorative, local SVG icons. Keep the link's visible label for accessibility. ?>
<?php if ($socialNetwork === 'whatsapp'): ?>
<svg class="social-icon" data-social-icon="whatsapp" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.3-4.8a8.5 8.5 0 1 1 16.2-4Z"/><path d="m8.2 7.4 1.4 2.5-1 1.1c.8 1.8 2.1 3.1 3.9 3.9l1.1-1 2.5 1.4c-.2 1.4-1 2-2.1 1.8-3.5-.5-6.8-3.8-7.3-7.3-.2-1.1.4-1.9 1.5-2.4Z"/></svg>
<?php elseif ($socialNetwork === 'instagram'): ?>
<svg class="social-icon" data-social-icon="instagram" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.4" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg>
<?php elseif ($socialNetwork === 'facebook'): ?>
<svg class="social-icon" data-social-icon="facebook" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14.5 21v-8h3l.5-4h-3.5V7c0-1 .4-1.5 1.5-1.5h2V2.2L15.4 2C12.4 2 11 3.8 11 6.5V9H8v4h3v8"/></svg>
<?php endif ?>
