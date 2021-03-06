
Name: app-firewall-dynamic
Epoch: 1
Version: 1.0.5
Release: 1%{dist}
Summary: Dynamic Firewall
License: GPLv3
Group: ClearOS/Apps
Packager: eGloo
Vendor: WikiSuite
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base

%description
The dynamic firewall app allows administrator to generate and implement time-based firewall rules based on webconfig events.

%package core
Summary: Dynamic Firewall - Core
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core
Requires: clearos-framework >= 7.3.10

%description core
The dynamic firewall app allows administrator to generate and implement time-based firewall rules based on webconfig events.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/firewall_dynamic
cp -r * %{buildroot}/usr/clearos/apps/firewall_dynamic/

install -d -m 0755 %{buildroot}/var/clearos/firewall_dynamic
install -d -m 0755 %{buildroot}/var/clearos/firewall_dynamic/triggers
install -d -m 0755 %{buildroot}/var/clearos/firewall_dynamic/triggers/webconfig_login
install -D -m 0755 packaging/10-firewall-dynamic %{buildroot}/etc/clearos/firewall.d/10-firewall-dynamic
install -D -m 0644 packaging/firewall-dynamic.cron %{buildroot}/etc/cron.d/app-firewall-dynamic
install -D -m 0644 packaging/openvpn.xml %{buildroot}/var/clearos/firewall_dynamic/rules/openvpn.xml
install -D -m 0750 packaging/rule_openvpn.php %{buildroot}/var/clearos/firewall_dynamic/triggers/webconfig_login/openvpn.php
install -D -m 0750 packaging/rule_ssh.php %{buildroot}/var/clearos/firewall_dynamic/triggers/webconfig_login/ssh.php
install -D -m 0644 packaging/ssh.xml %{buildroot}/var/clearos/firewall_dynamic/rules/ssh.xml

%post
logger -p local6.notice -t installer 'app-firewall-dynamic - installing'

%post core
logger -p local6.notice -t installer 'app-firewall-dynamic-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/firewall_dynamic/deploy/install ] && /usr/clearos/apps/firewall_dynamic/deploy/install
fi

[ -x /usr/clearos/apps/firewall_dynamic/deploy/upgrade ] && /usr/clearos/apps/firewall_dynamic/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-firewall-dynamic - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-firewall-dynamic-core - uninstalling'
    [ -x /usr/clearos/apps/firewall_dynamic/deploy/uninstall ] && /usr/clearos/apps/firewall_dynamic/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/firewall_dynamic/controllers
/usr/clearos/apps/firewall_dynamic/htdocs
/usr/clearos/apps/firewall_dynamic/views

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/firewall_dynamic/packaging
%exclude /usr/clearos/apps/firewall_dynamic/unify.json
%dir /usr/clearos/apps/firewall_dynamic
%dir %attr(0755,webconfig,webconfig) /var/clearos/firewall_dynamic
%dir %attr(0755,webconfig,webconfig) /var/clearos/firewall_dynamic/triggers
%dir %attr(0755,webconfig,webconfig) /var/clearos/firewall_dynamic/triggers/webconfig_login
/usr/clearos/apps/firewall_dynamic/deploy
/usr/clearos/apps/firewall_dynamic/language
/usr/clearos/apps/firewall_dynamic/libraries
%config(noreplace) /etc/clearos/firewall.d/10-firewall-dynamic
%config(noreplace) /etc/cron.d/app-firewall-dynamic
%attr(0644,webconfig,webconfig) %config(noreplace) /var/clearos/firewall_dynamic/rules/openvpn.xml
%attr(0750,webconfig,webconfig) /var/clearos/firewall_dynamic/triggers/webconfig_login/openvpn.php
%attr(0750,webconfig,webconfig) /var/clearos/firewall_dynamic/triggers/webconfig_login/ssh.php
%attr(0644,webconfig,webconfig) %config(noreplace) /var/clearos/firewall_dynamic/rules/ssh.xml
