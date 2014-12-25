<?php
namespace Tango\Addon;

// php 本身 log 的问题在于不好收集
// 放一个简单通过 syslog-ng 收集的方法
//
// 问题在于 set_error_handler 只能有一个

class ErrorLog {
}
