# EventWillow translation defaults

Keep EventWillow wording in `lang/<locale>/<group>.php`. These are sparse catalogs: include only values that differ from EventSchedule, or keys added by EventWillow. Keep translation keys and placeholders unchanged; review “schedule” in context so verbs and timetable references retain their meaning.

Loading order is upstream `resources/lang`, this module, then administrator overrides published under `app.lang_overrides_path`. Later layers win. The translations editor uses the combined upstream and EventWillow catalogs as its default, so reverting an administrator edit restores EventWillow wording. Publishing administrator overrides never rewrites this module.

The initial extraction preserves the wording from EventWillow main commit `f70e834196ea85d18f1d056f84b8437da04fb881`. The upstream catalogs were restored byte-for-byte from `39dc63781b013c1c95e5c65f25a5fe3126a694fc`, the EventSchedule version already included in main. This branch does not include the later upstream development sync.

During an upstream update, take upstream changes to `resources/lang`. Check whether upstream removed or changed the meaning of a key overridden here; update the corresponding EventWillow value when necessary. New uncustomized keys automatically use upstream text.
