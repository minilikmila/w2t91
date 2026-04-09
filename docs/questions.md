#1.
question: The Prompt specifies queue-based enrollment with configurable approval workflows of up to 3 review levels and conditional branching, but does not define how workflow rules are authored or evaluated.
assumption: Approval workflows will be implemented as configurable rule sets attached to enrollment queues, with conditional branching based on learner attributes such as age and guardian contact presence.
solution: Implemented approval workflow metadata supporting up to 3 sequential review levels and conditional branch evaluation. If a learner is a minor, the workflow requires a second approver and verifies a guardian contact field before advancing.

#2.
question: The Prompt allows bulk import from CSV/XLSX up to 10,000 rows per batch, but does not clarify how validation failures should be handled for partial invalid rows.
assumption: Bulk imports should process valid rows and report row-level validation failures without importing invalid rows, while keeping the batch operation atomic at the row level.
solution: Implemented CSV/XLSX import that validates each row independently, imports valid rows, and returns a structured import report listing failed rows and error reasons. Invalid rows are skipped without aborting the entire batch.

#3.
question: The Prompt requires precise coordinates visible only to authorized roles with obfuscated location data shown to others, but does not specify which roles are authorized.
assumption: Authorized roles include administrators and operational planners, while general users and field staff receive obfuscated coordinates with a display address label.
solution: Implemented role-based location disclosure so authorized roles receive stored coordinates and less privileged roles receive coordinates rounded to 2 decimal degrees plus a display address label.

#4.
question: The Prompt describes Hands-On Security Training exercises and cohort assignment publishing, but does not define how cohorts are represented or how exercises are assigned to learners.
assumption: Cohorts are represented as learner groups with assignment records, and exercises are published to cohort assignments that learners can query and complete.
solution: Implemented cohort assignment entities linking learners and exercises, with exercise publishing endpoints to assign configurable training modules to cohorts and action trails captured per learner attempt.

#5.
question: The Prompt requires local session/token issuance without external verifiers but does not specify token type, lifetime, or refresh behaviour.
assumption: Use internally signed tokens with a fixed lifetime and no refresh tokens, coupled with server-side session tracking for logout and account lockouts.
solution: Implemented JWT-style session tokens issued by the backend with a configurable expiration, stored session records, and invalidation on logout or account lockout.

#6.
question: The Prompt names roles, permissions, and authorization checks but does not define the specific role hierarchy or which permissions are required for each API.
assumption: Create standard roles such as admin, planner, reviewer, and field_agent, with explicit permission mappings for sensitive locations, enrollment approvals, and audit access.
solution: Implemented a role/permission model with mapped abilities and middleware enforcement, including granular access checks for location coordinate disclosure and workflow approvals.

#7.
question: The Prompt lists state transitions for enrollments but does not define when a cancelled enrollment is eligible for refund.
assumption: Refund transitions are allowed only for cancelled enrollments with payment records and if cancellation occurs before a configurable refund cutoff.
solution: Implemented cancelled-to-refunded transitions guarded by payment eligibility and cutoff rules, recording reason codes and actor details for each transition.

#8.
question: The Prompt mandates no external map services and local geofencing, but does not define the radius or sorting behavior for location proximity searches.
assumption: Use Haversine distance on stored coordinates and default to a 10 km geofence window, sorted ascending by computed distance.
solution: Implemented local distance calculations using coordinate math, supporting authorized coordinate retrieval and obfuscated location results for other roles.

#9.
question: The Prompt requires deduplication via deterministic fingerprints but does not specify conflict resolution when multiple learners share the same fingerprint.
assumption: Treat matching fingerprints as potential duplicates; create merge candidate records rather than automatically merging, and preserve separate learner records until manual review.
solution: Implemented fingerprint detection that flags possible duplicates and tracks matches in a deduplication queue, preserving audit history and prior values.

#10.
question: The Prompt demands encryption of sensitive fields at rest and PII masking in responses, but does not identify which fields are in scope or the masking format.
assumption: Encrypt PII fields such as email, phone, SSN, and guardian contact; mask standard responses to show only partial values for email and phone.
solution: Implemented field encryption for sensitive learner and user attributes and response masking that returns partial email/phone values by default.

#11.
question: The Prompt requires report generation and export without specifying export formats or report trigger methods.
assumption: Provide local export in CSV and JSON formats and allow reports to be generated on demand via API.
solution: Implemented report definition and generation endpoints that export requested data sets to CSV and JSON, with local storage of generated report metadata.

#12.
question: The Prompt calls for tamper-evident audit logging and queryable audit trails, but does not state how audit immutability is enforced.
assumption: Use append-only audit event records with hash chaining and immutable timestamps, plus query APIs for audit trail retrieval.
solution: Implemented audit_events as append-only records with prior-hash links and structured query endpoints, ensuring tamper-evidence through chained audit metadata.
