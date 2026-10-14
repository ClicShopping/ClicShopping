<?php
/**
 * Security: Prevent direct access
 */
header('HTTP/1.1 403 Forbidden');
exit('Direct access not permitted');
