<?php
require_once __DIR__ . "/admin-management.php";

function companyMatchesByName($conn, $companyName)
{
    $name = function_exists('mb_strtolower')
        ? mb_strtolower(trim($companyName), 'UTF-8')
        : strtolower(trim($companyName));
    $stmt = $conn->prepare("SELECT company_id, status FROM companies WHERE LOWER(TRIM(company_name)) = ? ORDER BY company_id LIMIT 2");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    return $matches;
}

function checkImportDuplicate($conn, $type, array $row)
{
    if ($type === 'company') {
        $matches = companyMatchesByName($conn, (string) $row['company_name']);
        return $matches ? 'duplicate: A company with this normalized name already exists; use the company form to review before adding another.' : null;
    }

    $email = managementEmail($row['email'] ?? '');
    if (managementEmailExists($conn, $email)) {
        return 'duplicate: Email already belongs to an existing account.';
    }
    if ($type === 'employer') {
        $matches = companyMatchesByName($conn, (string) ($row['company_name'] ?? ''));
        if (count($matches) !== 1 || $matches[0]['status'] !== 'active') {
            return count($matches) > 1
                ? 'invalid: Company name matches multiple records; select the company in the admin form.'
                : 'invalid: No single active company matches this name; create or review the company first.';
        }
        $row['company_id'] = (int) $matches[0]['company_id'];
    }
    return null;
}

function prepareImportRows($conn, $type, array $rows)
{
    $seenEmails = [];
    $seenCompanies = [];
    $prepared = [];
    foreach ($rows as $item) {
        $row = $item['data'];
        $errors = $item['errors'];
        if ($type !== 'company') {
            $email = managementEmail($row['email'] ?? '');
            if ($email !== '') {
                if (isset($seenEmails[$email])) {
                    $errors[] = 'Duplicate email in this CSV (first appears on row ' . $seenEmails[$email] . ').';
                } else {
                    $seenEmails[$email] = $item['row'];
                }
            }
        } else {
            $name = function_exists('mb_strtolower')
                ? mb_strtolower(trim((string) ($row['company_name'] ?? '')), 'UTF-8')
                : strtolower(trim((string) ($row['company_name'] ?? '')));
            if ($name !== '') {
                if (isset($seenCompanies[$name])) {
                    $errors[] = 'Duplicate company name in this CSV (first appears on row ' . $seenCompanies[$name] . ').';
                } else {
                    $seenCompanies[$name] = $item['row'];
                }
            }
        }
        if (!$errors) {
            $duplicate = checkImportDuplicate($conn, $type, $row);
            if ($duplicate !== null) {
                if (str_starts_with($duplicate, 'duplicate: ')) {
                    $errors[] = 'Skipped: ' . substr($duplicate, strlen('duplicate: '));
                } else {
                    $errors[] = 'Rejected: ' . substr($duplicate, strlen('invalid: '));
                }
            }
        }
        $prepared[] = [
            'row' => $item['row'],
            'data' => $row,
            'errors' => array_values(array_unique($errors)),
            'duplicate' => (bool) array_filter($errors, static function ($error) {
                return str_starts_with($error, 'Skipped:') || str_starts_with($error, 'Duplicate ');
            }),
        ];
    }
    return $prepared;
}

function importRowCounts(array $rows)
{
    $counts = ['insert' => 0, 'skip' => 0, 'reject' => 0];
    foreach ($rows as $item) {
        if (!$item['errors']) {
            $counts['insert']++;
        } elseif (!empty($item['duplicate'])) {
            $counts['skip']++;
        } else {
            $counts['reject']++;
        }
    }
    return $counts;
}
