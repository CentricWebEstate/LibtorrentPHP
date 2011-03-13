# Contributing to the development of LibtorrentPHP
Here's how you can contribute to the development of LibtorrentPHP:

## Step 1 - See a need, fill a need
Take a look around the LibtorrentPHP issues, see if there's something there you'd like to fix, or a missing feature you'd like to add.

Sometimes its best to start off small to get into the hang of things.

## Step 2 - Get your dev happening
First off you'll need to clone the repository. Check out the `develop` branch, that's where almost all of our pull requests get merged to (With the exception being security and bug fixes to previously released stable versions).

Next you'll want to make sure you've got <DEPENDENCYMANAGERNAME> installed. If you have: run <Dependency Management Command> when you're in the root folder of the project. This will install all of LibtorrentPHP's dependencies.

## Step 3 - Run the test suite
This step is super important as it makes sure that any differences in your operating environment that could cause some errors appear.

If you see any errors at this step, you should do your best to resolve them, and if you thing there's bugs, submit a pull request with the fixes.

Not every project creator or contributor can test every environment so this is a great step to help make LibtorrentPHP more stable.

## Step 4 - Make your changes
Start by making sure you've got `git flow` installed and that you've run `git flow init`.

The general options we use for LibtorrentPHP in git flow are as follows:
 - master for stable work
 - develop for next release work
 - feature/ for feature branches
 - release/ for release branches
 - hotfix/ for hotfixes
 - support/ for support branches
 - v for version prefixes

Now to begin your work, you'll need to decide what the change is that you're going to make. You'll need to use git flow to create the appropriate branch, whether it be a hotfix for a bug in the master branch, or feature for a new feature you want to develop. We won't except pull requests unless they are in an appropriately named branch that isn't master or develop.

## Step 5 - Tests and Testing
The next step is to create and/or modify the existing tests to cover all the code you've written. Depending on if you use TDD (Test Driven Development) or not, you might have already done this step.

Make sure you're tests are passing completely before moving on.

## Step 6 - Cleanup and pull request
Now that you've made your changes it's time to remove any code comments, <DEBUGINGFUNCTIONS> that do anything other than provide critical functionality or documentation of the project.

Push your changes to your local fork and you're nearly done.

## Step 7 - The pull request
The next step is now to create a pull request which is clean, simple and helps the contributors to quickly decide to merge the request.

A nice format might be the following:
 > **Purpose**
 > This pull request adds the following features to the LibtorrentPHP project:
 >  - Feature 1
 >  - Feature 2
 >
 > **Rational**
 > The reason for these changes is that it does (a) and it helps to fix bugs #3 and #4
 >

A request like the above is easy to understand and helps get your changes into LibtorrentPHP faster.

## Step 8 - Take a bow
Well done, you've made a great effort to contribute nicely to an open source project. Well done Sir/Madam. Well done.



Taken from jaitaiwan's [Generic Contribution File](https://bitbucket.org/snippets/jaitaiwan/6RKn).<br />
This contributing file is released under the Artisitc 2.0 licence.<br />
Copyright 2015 Daniel J Holmes (jaitaiwan). All Rights Reserved.<br />
The above notice and this line must accompany all copies and distributions of this file whether modified in part and/or in whole.<br />
