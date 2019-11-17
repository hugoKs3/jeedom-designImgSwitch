---
layout: default
title: Index
lang: en_US
---

# Description

Plugin to automatically change the background image of your design according to the weather.
Weather, sunrise and sunset information will be obtained from the official weather plugin.

# Installation

In order to use the plugin, you must download, install and activate it like any Jeedom plugin.
You will also need to install and configure the official weather plugin.

There is no configuration to perform at the plugin level.

# Device configuration

Le plugin se trouve dans le menu Plugins > Météo.
Après avoir créé un nouvel équipement, les options habituelles sont disponible.

Then select the designs for which you want to change the background image according to the weather and choose the weather device to use.
If no weather device is active, you will see an error message.

# Device activation

As soon as the configuration is done, you can activate the device and save it.
Selected designs will be immediately updated and this each time weather condition change and at sunrise and sunset.
No other action or scenario is needed.

# "Refresh" command

The device has a "Refresh" command to update manually the selected designs.
In normal condition, this command should nerver be used, background images will be automatically updated whenever is needed.

# Changelog

[Voir le changelog](./changelog)

# Support

Si malgré cette documentation et après voir lu les sujets en rapport avec le plugin sur [community]({{site.forum}}) vous ne trouvez pas de réponse à votre question, n'hésitez pas à créer un nouveau sujet en n'oubliant pas de mettre le tag du plugin ({{site.tagPlugin}}).
