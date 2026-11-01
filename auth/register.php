<?php
require_once __DIR__ . "/../includes/session.php";

$registrationTypes = ['student', 'professional', 'employer', 'company'];
$registrationType = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (is_string($_POST['role'] ?? null) && in_array($_POST['role'], $registrationTypes, true) ? $_POST['role'] : '')
    : 'student';
$error = '';
$errors = [];
$notice = isset($_GET['company_registered'])
    ? 'Company profile created. No login was created. To post jobs, an authorized recruiter must register and associate with this company, or an administrator can link a recruiter account.'
    : '';
$companyOptions = $conn->query("
    SELECT company_id, company_name, industry, location
    FROM companies
    WHERE status = 'active'
    ORDER BY company_name
");

$values = [
    'email' => '',
    'name' => '',
    'phone' => '',
    'js_location' => '',
    'skills' => '',
    'gender' => '',
    'dob' => '',
    'education' => '',
    'state' => '',
    'college_university' => '',
    'course' => '',
    'branch' => '',
    'current_year_semester' => '',
    'graduation_year' => '',
    'highest_qualification' => '',
    'current_previous_company' => '',
    'experience_years' => '',
    'expected_salary' => '',
    'recruiter_name' => '',
    'recruiter_phone' => '',
    'designation' => '',
    'company_mode' => '',
    'company_id' => '',
    'company_name' => '',
    'industry' => '',
    'website' => '',
    'official_email' => '',
    'company_phone' => '',
    'company_location' => '',
    'company_description' => '',
];

foreach ($values as $field => $unused) {
    if (isset($_POST[$field]) && is_string($_POST[$field])) {
        $maxLength = in_array($field, ['skills', 'company_description'], true) ? 10000 : 1000;
        $values[$field] = inputString($_POST[$field], $maxLength);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $errors = [];
    $accountType = in_array($registrationType, ['student', 'professional', 'employer'], true);
    $email = strtolower(trim($values['email']));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $termsAccepted = ($_POST['terms'] ?? '') === '1';

    if ($accountType) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 12 || strlen($password) > 72) {
            $errors[] = 'Use a password between 12 and 72 bytes.';
        }
        $duplicate = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $duplicate->bind_param("s", $email);
        $duplicate->execute();
        if ($email !== '' && $duplicate->get_result()->fetch_assoc()) {
            $errors[] = 'That email address is already registered.';
        }
    }

    if (!$termsAccepted) {
        $errors[] = 'Please accept the Terms and Conditions to continue.';
    }

    $phoneIsValid = static function ($phone) {
        return preg_match('/\A[0-9+(). -]{7,20}\z/', $phone)
            && preg_match_all('/[0-9]/', $phone) >= 7;
    };
    $required = static function ($field, $label) use (&$errors, $values) {
        if (trim($values[$field]) === '') {
            $errors[] = $label . ' is required.';
        }
    };

    if ($registrationType === 'student' || $registrationType === 'professional') {
        $required('name', 'Full name');
        $required('phone', 'Phone');
        $required('skills', 'Skills');
        $required('js_location', 'Location');
        if (strlen($values['name']) > 100 || strlen($values['phone']) > 15
            || strlen($values['skills']) > 5000 || strlen($values['js_location']) > 100
            || strlen($values['state']) > 100) {
            $errors[] = 'One or more profile fields exceed the allowed length.';
        }
        if ($values['phone'] !== '' && !$phoneIsValid($values['phone'])) {
            $errors[] = 'Enter a valid phone number.';
        }
        if (!in_array($values['gender'], ['', 'Male', 'Female', 'Other'], true)) {
            $errors[] = 'Select a valid gender.';
        }
        if ($values['dob'] !== '') {
            $dateParts = date_parse_from_format('Y-m-d', $values['dob']);
            if ($dateParts['error_count'] !== 0 || $dateParts['warning_count'] !== 0
                || !checkdate($dateParts['month'], $dateParts['day'], $dateParts['year'])) {
                $errors[] = 'Enter a valid date of birth.';
            }
        }
        if ($registrationType === 'student') {
            foreach ([
                'college_university' => 'College or university',
                'course' => 'Course',
                'branch' => 'Branch',
                'current_year_semester' => 'Current year or semester',
                'graduation_year' => 'Graduation year',
            ] as $field => $label) {
                $required($field, $label);
            }
            $year = filter_var($values['graduation_year'], FILTER_VALIDATE_INT);
            if ($year === false || $year < 1950 || $year > ((int) date('Y') + 10)) {
                $errors[] = 'Enter a valid graduation year.';
            }
            if (strlen($values['college_university']) > 180 || strlen($values['course']) > 150
                || strlen($values['branch']) > 150 || strlen($values['current_year_semester']) > 50) {
                $errors[] = 'One or more student fields exceed the allowed length.';
            }
        } else {
            $required('highest_qualification', 'Highest qualification');
            $required('experience_years', 'Experience');
            $experience = filter_var($values['experience_years'], FILTER_VALIDATE_FLOAT);
            if ($experience === false || $experience < 0 || $experience > 999.99) {
                $errors[] = 'Enter experience between 0 and 999.99 years.';
            }
            $salary = trim($values['expected_salary']);
            if ($salary !== '' && (!is_numeric($salary) || (float) $salary < 0 || (float) $salary > 9999999999.99)) {
                $errors[] = 'Enter a valid expected salary.';
            }
            if (strlen($values['highest_qualification']) > 150 || strlen($values['current_previous_company']) > 180) {
                $errors[] = 'One or more professional fields exceed the allowed length.';
            }
        }
    } elseif ($registrationType === 'employer') {
        $required('recruiter_name', 'Recruiter name');
        $required('recruiter_phone', 'Recruiter phone');
        $required('designation', 'Designation');
        if (!$phoneIsValid($values['recruiter_phone'])) {
            $errors[] = 'Enter a valid recruiter phone number.';
        }
        if (strlen($values['recruiter_name']) > 100 || strlen($values['recruiter_phone']) > 20
            || strlen($values['designation']) > 100) {
            $errors[] = 'One or more recruiter fields exceed the allowed length.';
        }
        if ($values['company_mode'] === 'existing') {
            $companyId = filter_var($values['company_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($companyId === false) {
                $errors[] = 'Select an active company.';
            }
        } elseif ($values['company_mode'] === 'new') {
            $required('company_name', 'Company name');
            if (strlen($values['company_name']) > 150 || strlen($values['industry']) > 100
                || strlen($values['website']) > 255 || strlen($values['official_email']) > 100
                || strlen($values['company_phone']) > 20 || strlen($values['company_location']) > 150
                || strlen($values['company_description']) > 10000) {
                $errors[] = 'One or more company fields exceed the allowed length.';
            }
            if ($values['company_phone'] !== '' && !$phoneIsValid($values['company_phone'])) {
                $errors[] = 'Enter a valid company phone number.';
            }
        } else {
            $errors[] = 'Choose an existing company or create a new company profile.';
        }
    } elseif ($registrationType === 'company') {
        $required('company_name', 'Company name');
        $required('company_phone', 'Company phone');
        if (strlen($values['company_name']) > 150 || strlen($values['industry']) > 100
            || strlen($values['website']) > 255 || strlen($values['official_email']) > 100
            || strlen($values['company_phone']) > 20 || strlen($values['company_location']) > 150
            || strlen($values['company_description']) > 10000) {
            $errors[] = 'One or more company fields exceed the allowed length.';
        }
        if (!$phoneIsValid($values['company_phone'])) {
            $errors[] = 'Enter a valid company phone number.';
        }
    } else {
        $errors[] = 'Select one of the available registration options.';
    }

    $companyWebsite = trim($values['website']);
    if (($registrationType === 'company' || ($registrationType === 'employer' && $values['company_mode'] === 'new'))
        && $companyWebsite !== ''
        && (!filter_var($companyWebsite, FILTER_VALIDATE_URL)
            || !in_array(strtolower((string) parse_url($companyWebsite, PHP_URL_SCHEME)), ['http', 'https'], true))) {
        $errors[] = 'Enter a valid HTTP or HTTPS company website.';
    }
    $companyEmail = trim($values['official_email']);
    if (($registrationType === 'company' || ($registrationType === 'employer' && $values['company_mode'] === 'new'))
        && $companyEmail !== '' && !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid company email address.';
    }

    $profilePic = '';
    $resume = '';
    $companyLogo = '';
    if (!$errors && in_array($registrationType, ['student', 'professional'], true)) {
        $profilePic = saveUploadedFile(
            'profile_pic',
            __DIR__ . '/../uploads/profile',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
            5 * 1024 * 1024,
            $error
        );
        if ($profilePic !== false) {
            $resume = saveUploadedFile(
                'resume',
                __DIR__ . '/../uploads/resumes',
                ['application/pdf' => 'pdf'],
                10 * 1024 * 1024,
                $error
            );
        }
    }
    $creatingCompany = $registrationType === 'company'
        || ($registrationType === 'employer' && $values['company_mode'] === 'new');
    if (!$errors && $error === '' && $creatingCompany) {
        $companyLogo = saveUploadedFile(
            'company_logo',
            __DIR__ . '/../uploads/company-logos',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            2 * 1024 * 1024,
            $error
        );
    }
    if ($profilePic === false || $resume === false || $companyLogo === false || $error !== '') {
        $errors[] = $error !== '' ? $error : 'One of the selected files could not be uploaded.';
    }

    if (!$errors) {
        $companyLockAcquired = false;
        $transactionStarted = false;
        $redirectUrl = null;
        try {
            if ($creatingCompany) {
                $lockResult = $conn->query("SELECT GET_LOCK('victory_phase2_company_management', 10) AS acquired")->fetch_assoc();
                $companyLockAcquired = isset($lockResult['acquired']) && (int) $lockResult['acquired'] === 1;
                if (!$companyLockAcquired) {
                    throw new RuntimeException('Company management is busy. Please try again.');
                }
            }

            $conn->begin_transaction();
            $transactionStarted = true;
            if ($creatingCompany) {
                $companyName = trim($values['company_name']);
                $duplicateCompany = $conn->prepare("
                    SELECT company_id FROM companies
                    WHERE LOWER(TRIM(company_name)) = LOWER(?) LIMIT 1
                ");
                $duplicateCompany->bind_param("s", $companyName);
                $duplicateCompany->execute();
                if ($duplicateCompany->get_result()->fetch_assoc()) {
                    throw new DomainException('A company with this name already exists. Select it from the recruiter company list instead.');
                }
            }

            if ($registrationType === 'company' || ($registrationType === 'employer' && $values['company_mode'] === 'new')) {
                $companyName = trim($values['company_name']);
                $industry = trim($values['industry']);
                $website = trim($values['website']);
                $officialEmail = strtolower(trim($values['official_email']));
                $companyPhone = trim($values['company_phone']);
                $companyLocation = trim($values['company_location']);
                $companyDescription = trim($values['company_description']);
                $logo = is_string($companyLogo) ? $companyLogo : '';
                $insertCompany = $conn->prepare("
                    INSERT INTO companies
                        (company_name, industry, website, official_email, phone, location, description, logo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertCompany->bind_param(
                    "ssssssss",
                    $companyName,
                    $industry,
                    $website,
                    $officialEmail,
                    $companyPhone,
                    $companyLocation,
                    $companyDescription,
                    $logo
                );
                $insertCompany->execute();
                $companyId = (int) $insertCompany->insert_id;
            } elseif ($registrationType === 'employer') {
                $companyId = (int) $values['company_id'];
                $existingCompany = $conn->prepare("
                    SELECT company_name, industry, location, description
                    FROM companies
                    WHERE company_id = ? AND status = 'active'
                    FOR UPDATE
                ");
                $existingCompany->bind_param("i", $companyId);
                $existingCompany->execute();
                $company = $existingCompany->get_result()->fetch_assoc();
                if (!$company) {
                    throw new DomainException('The selected company is unavailable. Choose an active company.');
                }
                $companyName = $company['company_name'];
                $industry = $company['industry'] ?? '';
                $companyLocation = function_exists('mb_substr')
                    ? mb_substr((string) ($company['location'] ?? ''), 0, 100, 'UTF-8')
                    : substr((string) ($company['location'] ?? ''), 0, 100);
                $companyDescription = $company['description'] ?? '';
            }

            if ($registrationType !== 'company') {
                $role = $registrationType === 'employer' ? 'employer' : 'jobseeker';
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $insertUser = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                $insertUser->bind_param("sss", $email, $passwordHash, $role);
                $insertUser->execute();
                $userId = (int) $insertUser->insert_id;

                if ($role === 'jobseeker') {
                    $profileType = $registrationType;
                    $name = trim($values['name']);
                    $phone = trim($values['phone']);
                    $skills = trim($values['skills']);
                    $location = trim($values['js_location']);
                    $gender = $values['gender'];
                    $dateOfBirth = $values['dob'] !== '' ? $values['dob'] : null;
                    $state = trim($values['state']);
                    $profilePicture = is_string($profilePic) ? $profilePic : '';
                    $resumeFile = is_string($resume) ? $resume : '';
                    if ($profileType === 'student') {
                        $education = trim($values['course'] . ' - ' . $values['branch']);
                    } else {
                        $education = trim($values['highest_qualification']);
                    }
                    $education = function_exists('mb_substr')
                        ? mb_substr($education, 0, 100, 'UTF-8')
                        : substr($education, 0, 100);
                    $insertProfile = $conn->prepare("
                        INSERT INTO job_seekers
                            (user_id, profile_type, name, phone, skills, resume, location, profile_pic, gender, dob, education, state)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insertProfile->bind_param(
                        "isssssssssss",
                        $userId,
                        $profileType,
                        $name,
                        $phone,
                        $skills,
                        $resumeFile,
                        $location,
                        $profilePicture,
                        $gender,
                        $dateOfBirth,
                        $education,
                        $state
                    );
                    $insertProfile->execute();
                    $jsId = (int) $insertProfile->insert_id;

                    if ($profileType === 'student') {
                        $college = trim($values['college_university']);
                        $course = trim($values['course']);
                        $branch = trim($values['branch']);
                        $currentYear = trim($values['current_year_semester']);
                        $graduationYear = (int) $values['graduation_year'];
                        $insertSubtype = $conn->prepare("
                            INSERT INTO student_profiles
                                (js_id, college_university, course, branch, current_year_semester, graduation_year)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $insertSubtype->bind_param("issssi", $jsId, $college, $course, $branch, $currentYear, $graduationYear);
                    } else {
                        $qualification = trim($values['highest_qualification']);
                        $previousCompany = trim($values['current_previous_company']);
                        $experienceYears = (float) $values['experience_years'];
                        $expectedSalary = trim($values['expected_salary']);
                        $salaryValue = $expectedSalary === '' ? null : (float) $expectedSalary;
                        $insertSubtype = $conn->prepare("
                            INSERT INTO professional_profiles
                                (js_id, highest_qualification, current_previous_company, experience_years, expected_salary)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insertSubtype->bind_param("issdd", $jsId, $qualification, $previousCompany, $experienceYears, $salaryValue);
                    }
                    $insertSubtype->execute();
                } else {
                    $recruiterName = trim($values['recruiter_name']);
                    $recruiterPhone = trim($values['recruiter_phone']);
                    $designation = trim($values['designation']);
                    $about = (string) ($companyDescription ?? '');
                    $employerLocation = function_exists('mb_substr')
                        ? mb_substr((string) ($companyLocation ?? ''), 0, 100, 'UTF-8')
                        : substr((string) ($companyLocation ?? ''), 0, 100);
                    $insertEmployer = $conn->prepare("
                        INSERT INTO employers
                            (user_id, company_name, industry, location, about, recruiter_name, phone, designation, company_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insertEmployer->bind_param(
                        "isssssssi",
                        $userId,
                        $companyName,
                        $industry,
                        $employerLocation,
                        $about,
                        $recruiterName,
                        $recruiterPhone,
                        $designation,
                        $companyId
                    );
                    $insertEmployer->execute();
                }
            }

            $conn->commit();
            $transactionStarted = false;
            if ($registrationType === 'company') {
                $redirectUrl = "register.php?company_registered=1";
            } else {
                $redirectUrl = "login.php?registered=1";
            }
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                    $transactionStarted = false;
                } catch (Throwable $rollbackException) {
                    error_log("Registration rollback failed: " . $rollbackException->getMessage());
                }
            }
            if ($exception instanceof DomainException) {
                $error = $exception->getMessage();
            } elseif ($exception instanceof mysqli_sql_exception && $exception->getCode() === 1062) {
                $error = $registrationType === 'company' || $values['company_mode'] === 'new'
                    ? 'A company or account with these details already exists. Review the company choice and try again.'
                    : 'That email address is already registered.';
            } else {
                error_log("Registration transaction failed: " . $exception->getMessage());
                $error = 'Registration could not be completed. Please check the details and try again.';
            }
        } finally {
            if ($companyLockAcquired) {
                try {
                    $conn->query("SELECT RELEASE_LOCK('victory_phase2_company_management')");
                } catch (Throwable $lockException) {
                    error_log("Company-management lock release failed: " . $lockException->getMessage());
                }
            }
        }
        if ($redirectUrl !== null) {
            header("Location: " . $redirectUrl, true, 303);
            exit();
        }
    }

    if ($error !== '' || $errors) {
        foreach ([
            [$profilePic, __DIR__ . '/../uploads/profile/'],
            [$resume, __DIR__ . '/../uploads/resumes/'],
            [$companyLogo, __DIR__ . '/../uploads/company-logos/'],
        ] as [$uploadedFile, $directory]) {
            if (is_string($uploadedFile) && $uploadedFile !== '') {
                $path = $directory . basename($uploadedFile);
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
    if ($errors) {
        $error = implode(' ', array_unique($errors));
    }
}

function registrationValue(array $values, $field)
{
    return e($values[$field] ?? '');
}

$renderCompanyFields = static function () use (&$values) {
    ?>
    <div class="form-grid">
        <label>Company name
            <input type="text" name="company_name" maxlength="150" value="<?php echo registrationValue($values, 'company_name'); ?>" required>
        </label>
        <label>Industry <span class="optional-label">Optional</span>
            <input type="text" name="industry" maxlength="100" value="<?php echo registrationValue($values, 'industry'); ?>">
        </label>
        <label>Website <span class="optional-label">Optional</span>
            <input type="url" name="website" maxlength="255" value="<?php echo registrationValue($values, 'website'); ?>">
        </label>
        <label>Official email <span class="optional-label">Optional</span>
            <input type="email" name="official_email" maxlength="100" value="<?php echo registrationValue($values, 'official_email'); ?>">
        </label>
        <label>Company phone
            <input type="tel" name="company_phone" maxlength="20" value="<?php echo registrationValue($values, 'company_phone'); ?>" required>
        </label>
        <label>Location <span class="optional-label">Optional</span>
            <input type="text" name="company_location" maxlength="150" value="<?php echo registrationValue($values, 'company_location'); ?>">
        </label>
        <label class="form-span">Company description <span class="optional-label">Optional</span>
            <textarea name="company_description" rows="4" maxlength="10000"><?php echo registrationValue($values, 'company_description'); ?></textarea>
        </label>
        <label class="form-span">Company logo <span class="optional-label">Optional · JPEG, PNG or WebP · 2 MB max</span>
            <input type="file" name="company_logo" accept="image/jpeg,image/png,image/webp">
        </label>
    </div>
    <?php
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an account | Victory</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/register.css?v=20261031-2">
    <link rel="stylesheet" href="../assets/css/design-system.css?v=20261031">
</head>
<body class="auth-page auth-register">
<main class="register-container">
    <aside class="register-left">
        <a class="register-brand" href="../index.php">Victory</a>
        <p>One network for your next career move and the people building great teams.</p>
        <ul>
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Find opportunities that fit</li>
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Build a professional profile</li>
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Connect with hiring teams</li>
        </ul>
    </aside>
    <section class="register-right" aria-labelledby="registration-title">
        <p class="register-eyebrow">Join the Victory network</p>
        <h1 id="registration-title">Create your profile</h1>
        <p class="register-intro">Choose the option that best describes you.</p>

        <?php if ($notice !== ''): ?><p class="register-notice" role="status"><?php echo e($notice); ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="form-error" role="alert"><?php echo e($error); ?></p><?php endif; ?>

        <form id="registration-form" method="POST" enctype="multipart/form-data">
            <?php echo csrfInput(); ?>
            <fieldset class="registration-choices">
                <legend class="sr-only">Choose registration type</legend>
                <?php
                $choices = [
                    'student' => ['Student', 'For current students and recent graduates', 'fa-user-graduate'],
                    'professional' => ['Employee / Experienced Professional', 'For experienced job seekers', 'fa-user-tie'],
                    'employer' => ['Employer / Recruiter', 'For recruiters hiring on behalf of a company', 'fa-users'],
                    'company' => ['Company / Organization', 'Create an organization profile without a login', 'fa-building'],
                ];
                foreach ($choices as $value => [$label, $description, $icon]):
                ?>
                    <label class="registration-choice">
                        <input type="radio" name="role" value="<?php echo e($value); ?>" <?php echo $registrationType === $value ? 'checked' : ''; ?>>
                        <span class="choice-content">
                            <i class="fa-solid <?php echo e($icon); ?>" aria-hidden="true"></i>
                            <span><strong><?php echo e($label); ?></strong><small><?php echo e($description); ?></small></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div data-registration-group="account">
                <h2>Sign-in details</h2>
                <div class="form-grid">
                    <label>Email address
                        <input type="email" name="email" autocomplete="email" maxlength="100" value="<?php echo registrationValue($values, 'email'); ?>" required>
                    </label>
                    <label>Password
                        <input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>
                        <small>Use 12 to 72 characters.</small>
                    </label>
                </div>
            </div>

            <div data-registration-group="seeker">
                <h2>Professional profile</h2>
                <div class="form-grid">
                    <label>Full name
                        <input type="text" name="name" maxlength="100" autocomplete="name" value="<?php echo registrationValue($values, 'name'); ?>" required>
                    </label>
                    <label>Phone
                        <input type="tel" name="phone" maxlength="15" autocomplete="tel" value="<?php echo registrationValue($values, 'phone'); ?>" required>
                    </label>
                    <label>Location
                        <input type="text" name="js_location" maxlength="100" autocomplete="address-level2" value="<?php echo registrationValue($values, 'js_location'); ?>" required>
                    </label>
                    <label>Skills
                        <input type="text" name="skills" maxlength="5000" value="<?php echo registrationValue($values, 'skills'); ?>" required>
                    </label>
                    <label>Gender <span class="optional-label">Optional</span>
                        <select name="gender">
                            <?php foreach (['' => 'Prefer not to say', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'] as $value => $label): ?>
                                <option value="<?php echo e($value); ?>" <?php echo $values['gender'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Date of birth <span class="optional-label">Optional</span>
                        <input type="date" name="dob" value="<?php echo registrationValue($values, 'dob'); ?>">
                    </label>
                    <label>State <span class="optional-label">Optional</span>
                        <input type="text" name="state" maxlength="100" value="<?php echo registrationValue($values, 'state'); ?>">
                    </label>
                </div>
                <div data-registration-group="student">
                    <h3>Education</h3>
                    <div class="form-grid">
                        <label>College / University
                            <input type="text" name="college_university" maxlength="180" value="<?php echo registrationValue($values, 'college_university'); ?>" required>
                        </label>
                        <label>Course
                            <input type="text" name="course" maxlength="150" value="<?php echo registrationValue($values, 'course'); ?>" required>
                        </label>
                        <label>Branch
                            <input type="text" name="branch" maxlength="150" value="<?php echo registrationValue($values, 'branch'); ?>" required>
                        </label>
                        <label>Current year / semester
                            <input type="text" name="current_year_semester" maxlength="50" value="<?php echo registrationValue($values, 'current_year_semester'); ?>" required>
                        </label>
                        <label>Graduation year
                            <input type="number" name="graduation_year" min="1950" max="<?php echo (int) date('Y') + 10; ?>" value="<?php echo registrationValue($values, 'graduation_year'); ?>" required>
                        </label>
                    </div>
                </div>
                <div data-registration-group="professional">
                    <h3>Experience and qualification</h3>
                    <div class="form-grid">
                        <label>Highest qualification
                            <input type="text" name="highest_qualification" maxlength="150" value="<?php echo registrationValue($values, 'highest_qualification'); ?>" required>
                        </label>
                        <label>Experience (years)
                            <input type="number" name="experience_years" min="0" max="999.99" step="0.01" value="<?php echo registrationValue($values, 'experience_years'); ?>" required>
                        </label>
                        <label>Current / previous company <span class="optional-label">Optional</span>
                            <input type="text" name="current_previous_company" maxlength="180" value="<?php echo registrationValue($values, 'current_previous_company'); ?>">
                        </label>
                        <label>Expected salary <span class="optional-label">Optional</span>
                            <input type="number" name="expected_salary" min="0" step="0.01" value="<?php echo registrationValue($values, 'expected_salary'); ?>">
                        </label>
                    </div>
                </div>
                <div class="form-grid file-fields">
                    <label>Profile picture <span class="optional-label">Optional · 5 MB max</span>
                        <input type="file" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp">
                    </label>
                    <label>Resume <span class="optional-label">Optional · PDF, 10 MB max</span>
                        <input type="file" name="resume" accept="application/pdf,.pdf">
                    </label>
                </div>
            </div>

            <div data-registration-group="employer">
                <h2>Recruiter details</h2>
                <div class="form-grid">
                    <label>Recruiter name
                        <input type="text" name="recruiter_name" maxlength="100" value="<?php echo registrationValue($values, 'recruiter_name'); ?>" required>
                    </label>
                    <label>Designation
                        <input type="text" name="designation" maxlength="100" value="<?php echo registrationValue($values, 'designation'); ?>" required>
                    </label>
                    <label>Contact phone
                        <input type="tel" name="recruiter_phone" maxlength="20" value="<?php echo registrationValue($values, 'recruiter_phone'); ?>" required>
                    </label>
                </div>
                <label class="company-mode-label">Company association
                    <select id="company-mode" name="company_mode" required>
                        <option value="">Choose an option</option>
                        <option value="existing" <?php echo $values['company_mode'] === 'existing' ? 'selected' : ''; ?>>Associate with an existing company</option>
                        <option value="new" <?php echo $values['company_mode'] === 'new' ? 'selected' : ''; ?>>Create a new company profile</option>
                    </select>
                </label>
                <div data-registration-group="employer-existing">
                    <label>Existing company
                        <select name="company_id" required>
                            <option value="">Select an active company</option>
                            <?php while ($companyOption = $companyOptions->fetch_assoc()): ?>
                                <option value="<?php echo (int) $companyOption['company_id']; ?>" <?php echo (string) $values['company_id'] === (string) $companyOption['company_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($companyOption['company_name'] . ($companyOption['industry'] ? ' · ' . $companyOption['industry'] : '')); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </label>
                    <p class="form-hint">Choose the organization explicitly. Victory does not associate recruiters based on a matching company name.</p>
                </div>
                <div data-registration-group="employer-new">
                    <h3>New company profile</h3>
                    <?php $renderCompanyFields(); ?>
                </div>
            </div>

            <div data-registration-group="company">
                <h2>Organization details</h2>
                <p class="company-access-note"><strong>Company profiles do not create a login.</strong> To access recruiter tools and post jobs, an authorized recruiter must create an Employer / Recruiter account and associate with this company, or an administrator can link an authorized recruiter.</p>
                <?php $renderCompanyFields(); ?>
            </div>

            <label class="terms">
                <input type="checkbox" name="terms" value="1" required <?php echo ($_POST['terms'] ?? '') === '1' ? 'checked' : ''; ?>>
                I agree to the Terms and Conditions
            </label>
            <button class="register-btn" type="submit">Continue</button>
            <p class="login-link">Already have an account? <a href="login.php">Sign in</a></p>
        </form>
    </section>
</main>
<script>
(() => {
    const form = document.getElementById('registration-form');
    const roleInputs = form.querySelectorAll('input[name="role"]');
    const companyMode = document.getElementById('company-mode');
    const visibleGroups = {
        account: ['student', 'professional', 'employer'],
        seeker: ['student', 'professional'],
        student: ['student'],
        professional: ['professional'],
        employer: ['employer'],
        company: ['company']
    };

    function showGroup(group, visible) {
        form.querySelectorAll(`[data-registration-group="${group}"]`).forEach((section) => {
            section.hidden = !visible;
            section.querySelectorAll('input, select, textarea, button').forEach((control) => {
                control.disabled = !visible;
            });
        });
    }

    function updateForm() {
        const selected = form.querySelector('input[name="role"]:checked')?.value || 'student';
        Object.keys(visibleGroups).forEach((group) => showGroup(group, visibleGroups[group].includes(selected)));
        const mode = companyMode && !companyMode.disabled ? companyMode.value : '';
        showGroup('employer-existing', selected === 'employer' && mode === 'existing');
        showGroup('employer-new', selected === 'employer' && mode === 'new');
        const submit = form.querySelector('.register-btn');
        submit.textContent = selected === 'company' ? 'Create company profile' : 'Create account';
    }

    roleInputs.forEach((input) => input.addEventListener('change', updateForm));
    companyMode.addEventListener('change', updateForm);
    updateForm();
})();
</script>
</body>
</html>
