# Forge Bootstrap 5 Theme

This starter kit helps you create and develop a custom **bootstrap_forge** sub-theme for Drupal using **Bootstrap 5**, **SASS**, and **Webpack**, running inside **DDEV**.

---

## 1. Create Sub-theme

The sub-theme creation process involves these steps:

1. Navigate to the `bootstrap_forge` directory:

```
cd web/themes/contrib/bootstrap_forge
```

2. Make the script executable:

```
chmod +x scripts/create_subtheme.sh
```

3. Run the sub-theme creation script:

```
./scripts/create_subtheme.sh
```

4. Follow the on-screen prompts to name your sub-theme.

After completion, your directory structure should look like this:

```
Drupal root (web/)
├── themes
│   ├── contrib
│   │   ├── bootstrap_barrio
│   │   └── bootstrap_forge
│   └── custom
│       └── mycustom_bootstrap_forge   # Your sub-theme name
```

---

## 2. Clean Up

After creating your sub-theme, you can remove the starter kit:

```
composer remove drupal/bootstrap_forge
```

---

## 3. Development Setup

### 3.1 Install Node Dependencies

Navigate to your sub-theme directory and install dependencies:

```
cd web/themes/custom/mycustom_bootstrap_forge
ddev npm install
```

---

## 4. Running Webpack Inside DDEV

To run Webpack tasks inside DDEV, use:

```
ddev exec "cd web/themes/custom/mycustom_bootstrap_forge && npm run build:dev"
```

> This works **even if you are not currently inside** `web/themes/custom/mycustom_bootstrap_forge`.

---

## 5. Fixing SASS Warning Spam

If you see a lot of warnings after running `npm run build:dev`, install a compatible SASS version:

```
ddev npm install sass@1.77.6 --save-dev
```

---

## Notes

* This setup is intended for **Bootstrap 5** with **SASS**.
* All Node and Webpack commands should be run through **DDEV**.
* Make sure your sub-theme is enabled in Drupal after creation.
* Make sure to install bootstrap_barrio independently to keep it after removing forge starter kit

---

