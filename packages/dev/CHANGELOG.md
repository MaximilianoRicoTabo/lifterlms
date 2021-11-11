@lifterlms/dev CHANGELOG
========================

v0.0.2 - 2011-11-10
-------------------

+ Added flag `--links` to `changelog write` command in order to allow default flag configuration via the `.yml` config file. The flag is enabled by default for public repos and disabled for private ones.
+ Fixed an OSX issue encountered in the `changelog write` command resulting from the use of `xargs -d`.
+ Fixed an issue causing `changelog version next` to fail when passing the `--preid` flag.
+ Don't provide a default value to the `update-version` command option `--preid`.


v0.0.1 - 2011-11-05
-------------------

+ Initial release.
