=== Repo Pop ===
Contributors: regionallyfamous
Tags: github, repository, block, embed
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn a public GitHub repository into a playful WordPress project showcase.

== Description ==

Repo Pop adds a dynamic block for displaying public GitHub repositories as mini project showcases. Paste a repository URL, choose which details to show, and the block renders a larger landing-page-style section with server-side data from the public GitHub REST API.

The block can show the repository title, owner avatar, owner name, summary, homepage, language, topics, stars, forks, open issues, license, created date, updated date, last push date, default branch, visibility, archived status, and GitHub link.

Choose from three layouts: Hero Stack, Bento Board, and Terminal Zine. Hero Stack is a bold landing-page replacement, Bento Board turns the same GitHub data into modular panels, and Terminal Zine gives repo pages a playful developer-console feel.

GitHub responses are cached in WordPress transients for 12 hours by default.

== Installation ==

1. Upload the `repo-pop` folder to the `/wp-content/plugins/` directory.
2. Activate Repo Pop through the Plugins screen in WordPress.
3. Insert the Repo Pop block and paste a public GitHub repository URL.

== Frequently Asked Questions ==

= Does Repo Pop support private repositories? =

No. Version 0.1.0 supports public GitHub repositories only.

= Does Repo Pop expose a GitHub token in the browser? =

No. The block uses unauthenticated public GitHub API requests from WordPress server-side rendering.

== Changelog ==

= 0.1.0 =

* Initial release.
