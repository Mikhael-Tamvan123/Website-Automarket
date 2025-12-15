# admin/.htaccess
# Restrict access to admin folder

# Disable directory listing
Options -Indexes

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# Block access to sensitive files
<FilesMatch "\.(inc|sql|log|config|bak|tmp)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# admin/.htaccess
Options -Indexes

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# Block sensitive files
<FilesMatch "\.(inc|sql|log|config|bak|tmp)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Simple protection - redirect non-logged in users to main login
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Allow CSS, JS, images
    RewriteCond %{REQUEST_URI} \.(css|js|jpg|jpeg|png|gif|ico|woff|woff2|ttf|eot)$ [NC]
    RewriteRule ^ - [L]
    
    # If trying to access admin area without proper referer, redirect to main site
    RewriteCond %{HTTP_REFERER} !^https?://(www\.)?localhost/automarket/ [NC]
    RewriteCond %{REQUEST_URI} ^/admin/
    RewriteCond %{REQUEST_URI} !^/admin/logout\.php$
    RewriteRule ^(.*)$ ../index.php [L,R]
</IfModule>