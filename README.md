# Group by Field Widget

## Overview
Provides a widget for entity references and allows users to group options into collapsible details elements.

## Use case
Say you need to create a field that lists all the displays you have across all university campuses. So you create a 'Display' content type with a Field called facilities. A 'Facility' content type with field called campus. Lastly, a 'Campus' taxonomy vocabulary. With this module your displays field can be group a number of nested fields. You can group by Facility or you can group by Campus which is an entity reference in your facility content type.

## Installation/Configuration
 - Add an entity reference field to node
 - Head to manage form display, and change the widget to 'Entity reference group by field widget'
 - If your field is multi-value, it will use checkboxes, otherwise it will use radios.
## Inspiration
1. [project/grouped_checkboxes](https://www.drupal.org/project/grouped_checkboxes)
2. [project/entity_reference_tree](https://www.drupal.org/project/entity_reference_tree)



Current maintainers:
 * Jalil Floyd (Jalite1991) - https://www.drupal.org/u/Jalite1991
