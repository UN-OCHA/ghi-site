# AGENTS.md - Guidelines for Agentic Coding

This file contains build commands, code style guidelines, and conventions for agentic coding agents working in the GHI (Global Humanitarian Impact) Drupal site repository.

## Project Overview

GHI is a Drupal 10+ site built on the OCHA Common Design base theme. It uses:
- **Backend**: Drupal 10 with custom modules in `html/modules/custom/`
- **Frontend**: Common Design subtheme with SCSS in `html/themes/custom/common_design_subtheme/`
- **Build System**: Docksal (Docker-based development environment)
- **Package Management**: Composer (PHP), npm (Node.js)

## Build / Lint / Test Commands

### Environment Setup
```bash
# Initialize the development environment
fin init-site

# Install theme dependencies
cd html/themes/custom/common_design_subtheme && npm install
```

### PHP / Drupal Commands
```bash
# Clear Drupal caches
fin drush cr

# Run composer commands
fin composer install
fin composer update

# Code quality checks
fin exec vendor/bin/phpcs -p --report=full ./html/modules/custom --extensions=module/php,php/php,inc/php
fin exec vendor/bin/phpcs --standard=DrupalPractice --extensions=php,module,inc,install,test,profile,theme,css,info,txt,md,yml ./html/modules/custom/
```

### Theme / Frontend Commands
```bash
# Navigate to theme directory first
cd html/themes/custom/common_design_subtheme/

# SCSS development
npm run sass:watch          # Watch and compile SCSS with linting
npm run sass:compile-dev    # Compile SCSS for development
npm run sass:compile        # Compile SCSS for production
npm run sass:build          # Full build: lint + compile + postcss

# SCSS linting
npm run sass:lint           # Lint SCSS files
npm run sass:lint-fix       # Auto-fix SCSS linting issues

# JavaScript linting
npm run js:lint             # Lint JavaScript files

# SVG sprite generation
npm run svg:sprite          # Generate icon sprite from SVG files
```

### Testing Commands
```bash
# Run all PHPUnit tests
fin phpunit

# Run specific test suites
fin phpunit --testsuite unit
fin phpunit --testsuite functional
fin phpunit --testsuite kernel
fin phpunit --testsuite existing-site

# Run specific test
fin phpunit --filter testImportParagraphs

# Browser testing (Selenium Grid available)
fin vnc                    # Access noVNC for browser observation
fin selenium-grid          # Access Selenium Grid UI
```

## Code Style Guidelines

### PHP / Drupal Module Development

#### File Structure and Naming
- Module files: `html/modules/custom/{module_name}/{module_name}.module`
- Plugin classes: `src/Plugin/{type}/{name}.php`
- Entity classes: `src/Entity/{EntityName}.php`
- Helper classes: `src/Helpers/{HelperName}.php`
- Test classes: `tests/src/{Type}/{TestName}.php`

#### PHP Coding Standards
- Follow Drupal coding standards (use PHPCS for validation)
- Use strict typing in method signatures when possible
- Classes should have proper namespace declarations matching the module name
- Use dependency injection for services
- Implement proper interfaces for entities and plugins

#### Documentation Standards
- All hook implementations must have proper `@file` documentation
- Class methods should have `@param`, `@return`, and `@throws` documentation
- Use proper `@inheritdoc` tags when extending parent methods

#### Error Handling
- Use Drupal's exception handling patterns
- Log errors using `\Drupal::logger()->error()`
- Validate user input properly
- Handle edge cases gracefully

### SCSS / Frontend Development

#### File Organization
- Main entry point: `sass/styles.scss`
- GHI-specific styles: `sass/ghi/_ghi.scss`
- Component overrides: `sass/component-overrides/`
- Base styles: `sass/base/`
- Layout Builder styles: `sass/layout-builder/`

#### SCSS Conventions
- Use BEM methodology for class naming: `.block__element--modifier`
- Import partial files using `@import` at the top of files
- Use SCSS variables for colors, spacing, and typography
- Follow the existing folder structure for new components
- Use CSS custom properties (variables) for dynamic values

#### CSS Guidelines
- Mobile-first responsive design
- Use CSS Grid and Flexbox for layouts
- Avoid `!important` unless absolutely necessary
- Use logical units (rem, em, %) over pixels
- Ensure proper contrast ratios for accessibility

### JavaScript Development

#### Code Style (ESLint Configuration)
- Use ES6+ features when appropriate
- Single quotes for strings
- 2-space indentation
- No trailing whitespace
- Semicolons required
- Strict mode for functions

#### Drupal JavaScript Patterns
- Use Drupal behaviors for DOM manipulation
- Wrap code in `(function (Drupal, once) { ... })(Drupal, once);`
- Use `once()` for event binding to avoid duplicate handlers
- Follow Drupal's AJAX API for dynamic content

### Testing Guidelines

#### PHPUnit Tests
- Unit tests for pure PHP logic
- Kernel tests for Drupal API interactions
- Functional tests for user workflows
- ExistingSite tests for full integration testing

#### Test Structure
- Use descriptive test method names
- Arrange-Act-Assert pattern in tests
- Use proper test data providers
- Clean up test data in tearDown methods

## Import Conventions

### PHP Imports
```php
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\{module_name}\Helpers\{HelperName};
```

### SCSS Imports
```scss
@import "ghi/ghi-variables";
@import "base/base-typography";
@import "components/component-name";
```

## Naming Conventions

### PHP
- Classes: PascalCase (`PageTemplateManager`)
- Methods: camelCase (`calculateRatio`)
- Variables: camelCase (`planEntity`)
- Constants: UPPER_SNAKE_CASE (`MAX_DEPTH`)

### SCSS/CSS
- Classes: kebab-case with BEM (`.plan-entity__contributions--highlighted`)
- Variables: kebab-case (`$primary-color`)
- Files: kebab-case (`_plan-entity-table.scss`)

### JavaScript
- Variables/Functions: camelCase (`calculateRatio`)
- Constants: UPPER_SNAKE_CASE (`MAX_DEPTH`)
- Classes: PascalCase (`PageTemplateManager`)

## Security Considerations

- Always sanitize user input
- Use Drupal's render API for output
- Validate permissions in access callbacks
- Follow OWASP security guidelines
- Never commit credentials or API keys

## Performance Guidelines

- Use Drupal's caching layers appropriately
- Optimize database queries
- Implement lazy loading for images
- Minimize CSS and JavaScript in production
- Use CDN for static assets when possible

## Git Workflow

- Create feature branches from main
- Use descriptive commit messages
- Run linting and tests before committing
- Ensure all code quality checks pass

## Common Issues and Solutions

### SCSS Compilation
- If stylelint errors occur, run `npm run sass:lint-fix`
- Ensure Node.js version matches `package.json` requirements
- Clear theme cache after SCSS changes

### PHP Code Quality
- Run PHPCS before committing changes
- Use Drupal Practice standards for module development
- Ensure proper dependency injection

### Testing
- Use SQLite for test database (configured in phpunit.xml)
- Clear test data between test runs
- Use proper test isolation techniques

## Development Tools Integration

This project integrates with:
- **Docksal**: Docker-based development environment
- **PHPCS**: PHP code quality checking
- **Stylelint**: SCSS/CSS linting
- **ESLint**: JavaScript code quality
- **PHPUnit**: Unit and integration testing
- **Selenium Grid**: Browser automation testing

Always run the appropriate linting and testing commands before submitting changes to ensure code quality and consistency across the project.