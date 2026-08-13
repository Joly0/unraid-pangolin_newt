**Pangolin Newt**

Installs [Newt](https://docs.pangolin.net/manage/sites/understanding-sites) and adds a settings page that registers this Unraid server with your Pangolin instance as a **site**, so the resources running on this host (Docker containers, web UIs, services) can be published through Pangolin.

Configure it under **Settings > Pangolin Newt**:
- Enter your Pangolin endpoint URL, Newt ID and Newt secret (create a site in the Pangolin dashboard and choose Newt as the connection method).
- Click **Apply** to connect, or enable **Start automatically on boot**.

Newt runs entirely in user space: it creates no network interface and does not change the system resolver. With **Expose Docker containers** enabled, the Pangolin dashboard can list this server's containers when you pick targets for a resource.

If the [Pangolin CLI plugin](https://github.com/Joly0/unraid-pangolin_cli) is installed too, both share a single **Pangolin** entry in Settings with a tab each.

Source: https://github.com/fosrl/newt

---

*Unofficial, community-maintained plugin — not affiliated with or endorsed by Fossorial, Inc. "Pangolin" and the Pangolin logo are trademarks of Fossorial, Inc., used for identification only.*
