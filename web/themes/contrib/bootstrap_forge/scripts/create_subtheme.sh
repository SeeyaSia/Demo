#!/bin/bash
# Script to quickly create sub-theme.

echo '
+------------------------------------------------------------------------+
| With this script you could quickly create bootstrap_forge sub-theme     |
| In order to use this:                                                  |
| - bootstrap_forge theme (this folder) should be in the contrib folder   |
+------------------------------------------------------------------------+
'
echo 'The machine name of your custom theme? [e.g. mycustom_bootstrap_forge]'
read CUSTOM_BOOTSTRAP_FORGE

echo 'Your theme name ? [e.g. My custom bootstrap_forge]'
read CUSTOM_BOOTSTRAP_FORGE_NAME

if [[ ! -e ../../custom ]]; then
    mkdir ../../custom
fi
cd ../../custom
cp -r ../contrib/bootstrap_forge $CUSTOM_BOOTSTRAP_FORGE
cd $CUSTOM_BOOTSTRAP_FORGE
for file in *bootstrap_forge.*; do mv $file ${file//bootstrap_forge/$CUSTOM_BOOTSTRAP_FORGE}; done
for file in config/*/*bootstrap_forge*.*; do mv $file ${file//bootstrap_forge/$CUSTOM_BOOTSTRAP_FORGE}; done

# Remove create_subtheme.sh file, we do not need it in customized subtheme.
rm scripts/create_subtheme.sh

# mv {_,}$CUSTOM_BOOTSTRAP_FORGE.theme
grep -Rl bootstrap_forge .|xargs sed -i -e "s/bootstrap_forge/$CUSTOM_BOOTSTRAP_FORGE/"
sed -i -E "s/^name:.*/name: $CUSTOM_BOOTSTRAP_FORGE_NAME/" "$CUSTOM_BOOTSTRAP_FORGE.info.yml"
echo "# Check the themes/custom folder for your new sub-theme."