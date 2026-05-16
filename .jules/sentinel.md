## 2024-05-16 - Add CSRF Protection to Device Activation
**Vulnerability:** The device activation form (`view/page/activate.php`) lacked CSRF (Cross-Site Request Forgery) protection, allowing attackers to potentially link an arbitrary unit to a device via forged requests.
**Learning:** The project relies heavily on custom PHP structures without a centralized framework for forms, making it easy to forget CSRF protection.
**Prevention:** Ensure that all state-changing `POST` endpoints use `$_SESSION['csrf_token']` to generate and check a cryptographically secure token utilizing `hash_equals()`.
