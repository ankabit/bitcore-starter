# 🚀 BitCore Starter Template

A minimal starter template for building applications with the **BitCore PHP Framework**. This template provides a clean foundation with essential configurations and a sample module to help you get started quickly.

## 📋 Requirements

- PHP 8.1 or higher
- Composer
- MySQL/PostgreSQL database
- Web server (Apache/Nginx)

## 🛠️ Installation

### Using Composer (Recommended)

```bash
# Create new project from template
composer create-project bitcore/starter my-project-name
cd my-project-name

# Install dependencies
composer install

# Set up environment
cp .env.example .env
```

### Manual Installation

```bash
# Clone or download the starter template
git clone https://github.com/ankabit/bitcore.git my-project-name
cd my-project-name

# Install dependencies
composer install
```

## ⚙️ Configuration

### 1. Environment Setup

Configure your environment variables in `.env`:

```env
# Database Configuration
DB_HOST=localhost
DB_DATABASE=your_database
DB_USERNAME=your_username  
DB_PASSWORD=your_password

# Application Settings
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
```

### 2. Database Setup

```bash
# Run migrations (if any)
php vendor/bin/phinx migrate

# Or create your database manually
```

### 3. Web Server Configuration

Point your web server document root to the `public/` directory.

**For development, use PHP built-in server:**

```bash
php -S localhost:8000 -t public/
```

## 📁 Project Structure

```
bitcore-starter/
├── public/                 # Web root directory
│   └── index.php          # Application entry point
├── src/
│   ├── config/            # Core configuration files
│   │   ├── autoload.php   # Autoloader setup
│   │   └── constants.php  # Application constants
│   └── modules/           # Your application modules
│       └── Welcome/       # Sample welcome module
├── storage/               # Writable storage
│   ├── app/              # Application files
│   ├── cache/            # Cache files  
│   └── logs/             # Log files
├── tests/                 # Test files
├── composer.json          # Dependencies
└── README.md             # This file
```

## 🎯 Quick Start

### 1. Test the Installation

Visit your application URL. You should see the BitCore welcome page with:
- Framework information
- Sample module demonstration
- System status

### 2. Create Your First Module

```bash
# Generate a new module (if you have the CLI tool)
php artisan make:module MyModule

# Or create manually:
mkdir -p src/modules/MyModule
```

**Manual module creation example:**

```php
<?php
# src/modules/MyModule/MyModule.php
namespace Modules\MyModule;

use BitCore\Application\Services\Modules\AbstractModule;

class MyModule extends AbstractModule
{
    // ⚠️ REQUIRED properties
    protected $id = 'MyModule';
    protected $version = '1.0.0';
    
    // Optional properties  
    protected $name = 'My Custom Module';
    protected $description = 'Description of my module';
}
```

### 3. Add Routes

```php
<?php
# src/modules/MyModule/Config/routes.php
use Modules\MyModule\Actions\MyAction;

return [
    [
        'prefix' => '/api/my-module',
        'routes' => [
            'my-module.index' => [
                'method' => 'GET',
                'path' => '',
                'action' => [MyAction::class, 'index'],
            ],
        ],
    ],
];
```

## 🔧 Development

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test
./vendor/bin/phpunit tests/MyTest.php
```

### Code Quality

```bash
# Check code style
composer cs:check

# Fix code style
composer cs:fix

# Static analysis
composer analyze
```

### Available Scripts

```bash
composer test           # Run PHPUnit tests
composer cs:check       # Check code standards  
composer cs:fix         # Fix code standards
composer analyze        # Run PHPStan analysis
```

## 📦 What's Included

### Sample Welcome Module

The starter includes a fully functional **Welcome** module demonstrating:

- ✅ Proper module structure
- ✅ Action classes with dependency injection
- ✅ Route configuration
- ✅ HTML and JSON responses
- ✅ Unit tests
- ✅ BitCore framework integration

### Core Configuration

- **Autoloader setup** - PSR-4 autoloading configured
- **Constants definition** - Application-wide constants
- **Development tools** - PHPUnit, PHPStan, PHP_CodeSniffer
- **Directory structure** - Standard BitCore layout

### Development Environment

- **Testing framework** - PHPUnit with BitCore test helpers
- **Code quality tools** - Pre-configured linting and analysis  
- **Storage directories** - Logs, cache, and file storage ready

## 🚀 Next Steps

1. **Explore the Welcome Module** - Study `src/modules/Welcome/` for implementation examples
2. **Read the Documentation** - Visit [BitCore Repository](https://github.com/ankabit/bitcore) 
3. **Join the Community** - Get help and share experiences
4. **Build Your Application** - Start creating your custom modules

## 📚 Learn More

- **[BitCore Repository](https://github.com/ankabit/bitcore)** - Framework source code and documentation
- **[Issues](https://github.com/ankabit/bitcore/issues)** - Report bugs and request features  
- **[Discussions](https://github.com/ankabit/bitcore/discussions)** - Community discussions
- **[Wiki](https://github.com/ankabit/bitcore/wiki)** - Additional documentation

## 🤝 Support

- **Issues**: [GitHub Issues](https://github.com/ankabit/bitcore/issues)
- **Discussions**: [GitHub Discussions](https://github.com/ankabit/bitcore/discussions)  
- **Community**: [GitHub Repository](https://github.com/ankabit/bitcore)

## 📄 License

This starter template is open-sourced software licensed under the [MIT license](LICENSE).

---

**Built with ❤️ using BitCore Framework**

Get started building powerful, modular PHP applications today!