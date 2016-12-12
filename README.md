# Cloner

## Development guide

### Submodules
* This plugin brings with him an extra submodule (apart from DEV Dashboard): `git@bitbucket.org:incsub/content-copier-stub.git`
This submodule is in charge of the cloning process.

#### Why a submodule?
Because the library is useful for other plugins like New Blog Templates but it's only integrated in Cloner at the moment.

### Branches
There are two main branches:

* `development`: Every development work should be done here first 
* `master`: Whenever a new version is ready, merge `development` branch into this one and push

### Development workflow

Cloner contains a few automated tasks that helps the developer to make faster and less buggy releases.

#### Requirements:

1. Install nodejs: [https://github.com/joyent/node/wiki/installing-node.js-via-package-manager]
2. Install Grunt globally `sudo npm install -g grunt`
3. Execute `git submodule update --init --recursive` to download every submodule

#### Dependencies installation

Cloner requires a few node dependencies for development. Use `npm install` to install all of them.

#### Releasing versions

1. Make sure that the version in `mailchimp-sync.php` matches with the version in `package.json`, otherwise the build will fail.
2. If you have make changes in copier folder, you'll need to create a commit for that submodule also.
3. Update all Git submodules with `git submodule update --remote`
4. Now execute `npm run build`. A new folder called `build` will be created where you can grab the zip file for the new version.
5. Language files, JS Lint and text domains verification are done during the execution of this script so developer doesn't need to worry about these tasks.

Don't forget to create a new tag in Git!