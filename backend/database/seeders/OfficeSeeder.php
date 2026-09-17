<?php

namespace Database\Seeders;

use App\Models\MasterListItem;
use App\Models\Office;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Seeds the independent Cagayan de Oro offices used by all AGIS modules.
 */
class OfficeSeeder extends Seeder
{
    public const DEMO_OFFICES = [
        [
            'code' => 'AGIS-SYS', 'name' => 'AGIS System Administration', 'acronym' => 'AGIS',
            'sector' => 'System', 'contact_number' => null, 'head_name' => 'Juan dela Cruz',
            'description' => 'System administration and application governance.',
        ],
        [
            'code' => 'BPLD', 'name' => 'Business Permits and Licensing Division', 'acronym' => 'BPLD',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '0920-433-5156; 0976-184-3787',
            'head_name' => 'Oxanna S. Custodio', 'description' => 'New and renewal applications for business permits, including online applications.',
        ],
        [
            'code' => 'CAD', 'name' => 'City Accounting Department', 'acronym' => 'CAD',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '(088) 881-8767',
            'head_name' => 'Kirsten Kate F. Calvo', 'description' => 'Financial records, statements, payroll documents, BIR forms, and certifications.',
        ],
        [
            'code' => 'OCA', 'name' => 'Office of the City Administrator', 'acronym' => 'OCA',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '881-1369',
            'head_name' => 'Roy Hilario P. Raagas', 'description' => 'Supervises administrative operations and coordinates city offices, programs, and reforms.',
        ],
        [
            'code' => 'CBO', 'name' => 'City Budget Office', 'acronym' => 'CBO',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '(088) 557-5732; 0919-065-2001',
            'head_name' => 'Percy G. Salazar', 'description' => 'Reviews and consolidates annual and supplemental budgets and monitors appropriations.',
        ],
        [
            'code' => 'CFO', 'name' => 'City Finance Office', 'acronym' => 'CFO',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '(088) 881-2756; 0908-810-1609',
            'head_name' => 'Joanna Marie S. Sanz', 'description' => 'Manages city funds, collections, taxes, and disbursements.',
        ],
        [
            'code' => 'CGSO', 'name' => 'City General Services Office', 'acronym' => 'CGSO',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '0995-373-2087; (088) 857-3147',
            'head_name' => 'Joeffrey D. Namalata', 'description' => 'Procurement, supplies, infrastructure awards, archives, and records management.',
        ],
        [
            'code' => 'CIAS', 'name' => 'City Internal Audit Office', 'acronym' => 'CIAS',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '(088) 881-2132; 0967-391-2813',
            'head_name' => 'Cherrybelle A. Lao', 'description' => 'Evaluates controls, operations, legal compliance, risk management, and governance.',
        ],
        [
            'code' => 'CLO', 'name' => 'City Legal Office', 'acronym' => 'CLO',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '(088) 857-2260',
            'head_name' => 'Kenneth O. Tamala', 'description' => 'Provides legal advice, represents the city, and drafts ordinances, contracts, and legal instruments.',
        ],
        [
            'code' => 'HRMO', 'name' => 'Human Resource Management Office', 'acronym' => 'HRMO',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '(088) 857-3154; 857-3155; 0926-852-2905',
            'head_name' => 'Xsyclyn Faith B. Lumbatan', 'description' => 'Recruitment, appointments, personnel policies, training, and employee records.',
        ],
        [
            'code' => 'SMO', 'name' => 'Secretary to the Mayor', 'acronym' => 'SMO',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '857-7587; 0965-667-9604',
            'head_name' => 'Xsyclyn Faith B. Lumbatan', 'description' => 'Processes requests for financial, medical, burial, rice, and transportation assistance.',
        ],
        [
            'code' => 'PLEB', 'name' => 'People’s Law Enforcement Board', 'acronym' => 'PLEB',
            'sector' => 'Administration, Finance and Legal', 'contact_number' => '857-4026; 857-2258; 0917-611-9324',
            'head_name' => 'Josefina G. Bacal', 'description' => 'Receives and decides administrative complaints against members of the police force.',
        ],
        [
            'code' => 'CASS', 'name' => 'City Assessment Department', 'acronym' => 'CASS',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => '0956-770-2338',
            'head_name' => 'Chit Leonelle Isaiah R. Bañas', 'description' => 'Real-property appraisal, assessment, tax mapping, and property records.',
        ],
        [
            'code' => 'CENG', 'name' => 'City Engineering Department', 'acronym' => 'CENG',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => '0906-803-2555; (088) 881-8836',
            'head_name' => 'Joel V. Momongan', 'description' => 'Plans, builds, repairs, and maintains roads, bridges, and public infrastructure.',
        ],
        [
            'code' => 'CED', 'name' => 'City Equipment Depot', 'acronym' => 'CED',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => '(088) 858-6134; 0917-145-8499',
            'head_name' => 'Vincent W. Ranile', 'description' => 'Manages and maintains the city’s light and heavy equipment.',
        ],
        [
            'code' => 'CHUDD', 'name' => 'City Housing and Urban Development', 'acronym' => 'CHUDD',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => '0955-088-0297; (088) 881-8724',
            'head_name' => 'John W. Asuncion', 'description' => 'Housing, shelter, and security-of-tenure programs for underprivileged residents.',
        ],
        [
            'code' => 'CLENRO', 'name' => 'City Local Environment and Natural Resources Office', 'acronym' => 'CLENRO',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => '(088) 881-7103; 0906-547-5624',
            'head_name' => 'Elvisa B. Mabelin', 'description' => 'Environmental protection, forests, watersheds, seedlings, and pollution-control coordination.',
        ],
        [
            'code' => 'CMISID', 'name' => 'City Management Information Systems and Innovation Department', 'acronym' => 'CMISID',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => '(088) 855-0376; 0926-755-6305',
            'head_name' => 'Zelfred Anthony T. Cocon', 'description' => 'Develops and maintains city government ICT systems, services, and technology initiatives.',
        ],
        [
            'code' => 'CPDO', 'name' => 'City Planning and Development Office', 'acronym' => 'CPDO',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => 'Zoning: 0999-996-4116; M&E: 0999-996-9776',
            'head_name' => 'Ardines C. Cabrera', 'description' => 'City development planning, zoning, investment programming, and project monitoring.',
        ],
        [
            'code' => 'OCBO', 'name' => 'Office of the City Building Official', 'acronym' => 'OCBO',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => '(088) 881-2131; (088) 857-2687; 0975-038-2125',
            'head_name' => 'Rosanna D. Rodriguez', 'description' => 'Processes building, occupancy, fencing, and electrical permits and conducts inspections.',
        ],
        [
            'code' => 'RTA', 'name' => 'Roads and Traffic Administration', 'acronym' => 'RTA',
            'sector' => 'Planning, Infrastructure, Technology and Environment', 'contact_number' => '0906-964-1092',
            'head_name' => 'Nonito A. Oclarit', 'description' => 'Traffic management, traffic-law enforcement, and clearing road obstructions.',
        ],
        [
            'code' => 'CHD', 'name' => 'City Health Department', 'acronym' => 'CHD',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '(088) 880-3199',
            'head_name' => 'Rachel D. Dilla', 'description' => 'Public-health, preventive, and curative services across the city’s barangays.',
        ],
        [
            'code' => 'CHIO', 'name' => 'City Health Insurance Office', 'acronym' => 'CHIO',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '(088) 881-0570; 0927-075-5708',
            'head_name' => 'Roxanne Mae A. Ravidas', 'description' => 'Facilitates PhilHealth enrollment and health-insurance support for qualified indigents.',
        ],
        [
            'code' => 'JRBGH', 'name' => 'J.R. Borja General Hospital', 'acronym' => 'JRBGH',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '(088) 880-1070; 0995-496-8279',
            'head_name' => 'Michael June C. Perez', 'description' => 'Hospital, medical, dental, and emergency healthcare services.',
        ],
        [
            'code' => 'CCCDO', 'name' => 'City College of Cagayan de Oro', 'acronym' => 'CCCDO',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '0917-795-2193; 0917-774-2177',
            'head_name' => 'Jestoni P. Babia', 'description' => 'Technical-vocational, diploma, degree, and emerging-technology programs.',
        ],
        [
            'code' => 'CPL', 'name' => 'City Public Library', 'acronym' => 'CPL',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '(088) 882-3079; 0935-893-9054',
            'head_name' => 'Loreta A. Deloso', 'description' => 'Free reading, research, e-library, children’s, and local-history services.',
        ],
        [
            'code' => 'CSO', 'name' => 'City Scholarships Office', 'acronym' => 'CSO',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '0929-819-1819',
            'head_name' => 'Richel Petalcurin-Dahay', 'description' => 'Tertiary scholarships and student development for qualified Kagay-anon students.',
        ],
        [
            'code' => 'CSWDD', 'name' => 'City Social Welfare and Development Department', 'acronym' => 'CSWDD',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '(088) 557-0376; 0935-944-8560',
            'head_name' => 'Anecia C. Tongson', 'description' => 'Social protection and assistance for vulnerable individuals and families.',
        ],
        [
            'code' => 'CCRO', 'name' => 'City Civil Registry Office', 'acronym' => 'CCRO',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '0906-828-8219',
            'head_name' => 'Aeneas Vicente C. Akut', 'description' => 'Registers and issues records of births, marriages, deaths, and other civil events.',
        ],
        [
            'code' => 'CID', 'name' => 'Community Improvement Division', 'acronym' => 'CID',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '(088) 881-0976; 0953-002-8776',
            'head_name' => 'Honorio G. Diputado Jr.', 'description' => 'Population development, livelihood, GAD, responsible parenting, and family-planning programs.',
        ],
        [
            'code' => 'OYDO', 'name' => 'Oro Youth Development Office', 'acronym' => 'OYDO',
            'sector' => 'Health, Education and Social Services', 'contact_number' => '0927-718-5519',
            'head_name' => 'Lord Saver D. Centina', 'description' => 'Coordinates youth programs and supports the youth council, SKs, and youth organizations.',
        ],
        [
            'code' => 'CAO', 'name' => 'City Agriculture Office', 'acronym' => 'CAO',
            'sector' => 'Agriculture, Business and Employment', 'contact_number' => '(088) 858-2908; (088) 557-7557',
            'head_name' => 'Bernie Daba', 'description' => 'Supports farmers and fisherfolk through training, organization, seedlings, and agricultural programs.',
        ],
        [
            'code' => 'CEEBDA', 'name' => 'City Economic Enterprises and Business Development Administration', 'acronym' => 'CEEBDA',
            'sector' => 'Agriculture, Business and Employment', 'contact_number' => 'Central: 0917-702-9542; Cogon: 0926-293-2600',
            'head_name' => 'Marianne G. Ragas', 'description' => 'Manages Cogon, Carmen, and Puerto markets and the City Slaughterhouse.',
        ],
        [
            'code' => 'EWBT', 'name' => 'East and West Bound Terminals and Public Market', 'acronym' => 'EWBT',
            'sector' => 'Agriculture, Business and Employment', 'contact_number' => '(088) 881-3202; 0917-771-1768',
            'head_name' => 'Allan A. Fernandez', 'description' => 'Manages terminals, public-market operations, stalls, and applicable fee collection.',
        ],
        [
            'code' => 'PESO', 'name' => 'Job Placement Bureau/PESO', 'acronym' => 'PESO',
            'sector' => 'Agriculture, Business and Employment', 'contact_number' => '(088) 850-1037; 0905-142-4324; 0956-425-6946',
            'head_name' => 'Kathleen Kate D. Sorilla', 'description' => 'Free employment facilitation, job matching, and employment-support programs.',
        ],
        [
            'code' => 'OROTIPC', 'name' => 'Oro Trade and Investments Promotions Center', 'acronym' => 'Oro-TIPC',
            'sector' => 'Agriculture, Business and Employment', 'contact_number' => '(088) 567-2977; 0966-144-1570',
            'head_name' => 'John Asuncion', 'description' => 'Promotes the city as an investment destination and assists prospective investors.',
        ],
        [
            'code' => 'CTCAO', 'name' => 'City Tourism and Cultural Affairs Office', 'acronym' => 'CTCAO',
            'sector' => 'Agriculture, Business and Employment', 'contact_number' => '(088) 859-3842; 0954-320-8939',
            'head_name' => 'Mark Kenneth Jalapadan', 'description' => 'Tourism assistance, promotions, tours, cultural programs, and tourism-establishment regulation.',
        ],
        [
            'code' => 'CDRRMD', 'name' => 'City Disaster Risk Reduction and Management Department', 'acronym' => 'CDRRMD',
            'sector' => 'Public Safety, Community and Other Services', 'contact_number' => '911; 0917-770-5044',
            'head_name' => 'Nick A. Jabagat', 'description' => 'Disaster preparedness, emergency response, rescue, rehabilitation, and risk reduction.',
        ],
        [
            'code' => 'CSU', 'name' => 'Civil Security Unit', 'acronym' => 'CSU',
            'sector' => 'Public Safety, Community and Other Services', 'contact_number' => '0936-680-0640',
            'head_name' => 'Carmelito P. Deloso', 'description' => 'Protects lives and property and assists law-enforcement and peace-and-order operations.',
        ],
        [
            'code' => 'CPSO', 'name' => 'City Public Services Office', 'acronym' => 'CPSO',
            'sector' => 'Public Safety, Community and Other Services', 'contact_number' => '(088) 880-4994; 0955-435-3999',
            'head_name' => 'Merlie M. Agustero', 'description' => 'Street cleaning, parks, city-hall sanitation, minor maintenance, and disaster support.',
        ],
        [
            'code' => 'CINFO', 'name' => 'City Information Office', 'acronym' => 'CIO',
            'sector' => 'Public Safety, Community and Other Services', 'contact_number' => '(088) 882-2376',
            'head_name' => 'Jade A. Adeser', 'description' => 'Releases public information and communicates city programs, projects, and activities.',
        ],
        [
            'code' => 'CVO', 'name' => 'City Veterinary Office', 'acronym' => 'CVO',
            'sector' => 'Public Safety, Community and Other Services', 'contact_number' => '(088) 857-2185; 0997-148-7356',
            'head_name' => 'Helen Ann P. Tacandong', 'description' => 'Animal health, rabies prevention, stray-animal control, and meat inspection.',
        ],
        [
            'code' => 'OCA-COMM', 'name' => 'Office for Community Affairs', 'acronym' => 'OCA',
            'sector' => 'Public Safety, Community and Other Services', 'contact_number' => '0998-967-1612',
            'head_name' => 'Cyril Ranile', 'description' => 'Coordinates barangay concerns, community linkages, assistance, and anti-drug initiatives.',
        ],
    ];

    public function run(): void
    {
        $officeTypes = MasterListItem::query()
            ->whereHas('masterList', fn ($query) => $query->where('code', 'OFFICE_TYPE'))
            ->pluck('id', 'code');

        foreach (self::DEMO_OFFICES as $office) {
            $attributes = Arr::except($office, ['head_name']);
            $name = strtoupper($office['name']);
            $typeCode = match (true) {
                str_contains($name, 'DEPARTMENT') => 'DEPARTMENT',
                str_contains($name, 'DIVISION') => 'DIVISION',
                str_contains($name, 'SECTION') => 'SECTION',
                str_contains($name, 'UNIT') => 'UNIT',
                str_contains($name, 'HOSPITAL'),
                str_contains($name, 'BOARD'),
                str_contains($name, 'COUNCIL') => 'SPECIAL_BODY',
                default => 'OFFICE',
            };
            $model = Office::withTrashed()->updateOrCreate(
                ['code' => $office['code']],
                [
                    ...$attributes,
                    'office_type_id' => $officeTypes[$typeCode] ?? null,
                    'is_active' => true,
                ],
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }

        if (config('demo.enabled') && ! config('demo.full_render_seeders')) {
            $demoCodes = array_column(self::DEMO_OFFICES, 'code');

            Office::query()
                ->whereNotIn('code', $demoCodes)
                ->each(function (Office $office): void {
                    $office->forceFill(['is_active' => false])->save();
                    $office->delete();
                });
        }
    }
}
