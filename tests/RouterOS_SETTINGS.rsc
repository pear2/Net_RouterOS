# The following file contains everything needed to adjust a fresh RouterOS
# install so that the tests run successfully with the provided PHPUnit settings.
# Extracted (and modified for portability) from the actual VM that tests are
# performed against.
#
# If you adjust the PHPUnit settings, you may want to adjust this file also.
#
# You may need to manually type out the following part,
# since it's required to establish ANY connection with the router.
# ```
# /ip dhcp-client add disabled=no interface=ether1
# ```
#
# NOTE: ether1 is assumed to be a host-only adapter;
#       ether2 is assumed to be a NAT adapter;
#       ether3 can be anything, including "not connected".
# NOTE: 192.168.57.0/24 is assumed to be the subnet
#       that the interface "local" uses.
#       Other interfaces should use another subnet.
#       You can safely replace "192.168.57." with another subnet if you must.
#
# The following may be pasted to a Winbox Terminal,
# as opposed to you having to type it out.

/interface ethernet
set [ find default-name=ether1 ] name=vm
set [ find default-name=ether2 ] name=net
set [ find default-name=ether3 ] name=local
/ip dhcp-client
add disabled=no interface=net
:delay 1s
/user
add address="" disabled=no group=full name=apifull password=apifull
add address="" disabled=no group=read name=api password=api
add address="" disabled=no group=full name=api-ANSI password="\E0\EF\E8"
/queue simple
add max-limit=100M/100M name=_TOTAL target=local
add max-limit=1M/2M name=A parent=_TOTAL target=192.168.57.2/32
add max-limit=1M/2M name=B parent=_TOTAL target=192.168.57.3/32
add max-limit=1M/2M name=C parent=_TOTAL target=192.168.57.4/32
add max-limit=1M/2M name=D parent=_TOTAL target=192.168.57.5/32
add name=_API_TESTING target=net
add name=INVALID parent=_API_TESTING target=[:resolve "invalid.ros.example.com"]
add name=SILENT parent=_API_TESTING target=[:resolve "silent.ros.example.com"]
