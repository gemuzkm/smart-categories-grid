# Smart Categories Grid

A powerful WordPress plugin for creating responsive category grids with advanced caching and customization options.

![Version](https://img.shields.io/badge/version-1.3-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-green.svg)
![License](https://img.shields.io/badge/license-GPL%20v2-yellow.svg)

## 🚀 Key Features

- 📱 **Fully Responsive Grid Layout**
  - Automatic adjustment for all screen sizes
  - Mobile-first approach
  - Print-friendly design

- ⚡ **Performance Optimized**
  - Advanced caching system
  - Lazy loading images
  - Optimized database queries
  - Minimal CSS/JS footprint

- 🎨 **Highly Customizable**
  - Flexible column settings (2-6 columns)
  - Customizable image styles
  - Hover effects
  - Border radius control
  - Default image fallback

- 🔒 **Secure & Reliable**
  - Input validation
  - XSS protection
  - SQL injection prevention
  - Error logging

## 📦 Installation

1. Download the latest release from GitHub
2. Upload to your WordPress site:
   ```bash
   wp plugin install smart-categories-grid.zip --activate
   ```
3. Configure via WordPress admin: Settings → Categories Grid

## 🎯 Usage

### Basic Implementation
```php
[categories_grid]
```

### Advanced Options
```php
[categories_grid 
    category_id="5" 
    force_update="true"
]
```

### Parameters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| category_id | int | 0 | Parent category ID |
| force_update | bool | false | Force cache refresh |

## ⚙️ Configuration

### Admin Settings
- **Grid Layout**
  - Number of columns (2-6)
  - Image border radius (0-50px)
  - Hover effects toggle

- **Performance**
  - Cache duration options
  - Force cache clearing
  - Default image settings

- **Display Options**
  - Category ordering
  - Image size control
  - Responsive breakpoints

## 🔧 Development

### Requirements
- PHP 7.4 or higher
- WordPress 5.0+
- MySQL 5.6+ or MariaDB 10.0+

### Setup Development Environment
```bash
# Clone repository
git clone https://github.com/your-username/smart-categories-grid.git

# Install dependencies
composer install

# Build assets
npm install && npm run build
```

### Directory Structure
```
smart-categories-grid/
├── assets/
│   ├── admin.css
│   ├── admin.js
│   ├── front.css
│   └── placeholder.png
├── includes/
│   ├── Cache.php
│   └── Settings.php
├── languages/
│   └── sc-grid.pot
├── LICENSE
└── README.md
```

## 🌐 Internationalization

The plugin supports multiple languages through WordPress's translation system:

- English (default)
- Translation-ready
- RTL support
- POT file included

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

## 📝 License

Distributed under the GPL v2 License. See `LICENSE` for more information.

---

Made with ❤️ for the WordPress community
