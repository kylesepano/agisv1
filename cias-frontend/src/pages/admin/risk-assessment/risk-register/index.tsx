import { useMemo, useState } from "react";
import { Download } from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { Heading, Paragraph } from "../../../../components/ui/typography";
import {
  RiskRegisterFilters,
  RiskRegisterStatCards,
  RiskRegisterTable,
} from "../components";
import { getRiskRegisterRecords } from "../service";
import { getRiskRegisterOverview } from "../risk-assessment-utils";
import { auditUniverseRecords } from "../../audit-universe/audit-universe-data";

const defaultCategory = "All Categories";
const defaultOffice = "All Offices";
const defaultRating = "All Risk Ratings";
const defaultAssessor = "All Assessors";

export function AdminRiskRegisterPage() {
  const [search, setSearch] = useState("");
  const [categoryFilter, setCategoryFilter] = useState(defaultCategory);
  const [officeFilter, setOfficeFilter] = useState(defaultOffice);
  const [ratingFilter, setRatingFilter] = useState(defaultRating);
  const [assessorFilter, setAssessorFilter] = useState(defaultAssessor);

  const records = getRiskRegisterRecords();

  const categoryOptions = useMemo(
    () => [
      { value: defaultCategory, label: defaultCategory },
      ...Array.from(new Set(records.map((record) => record.category))).map(
        (category) => ({
          value: category,
          label: category,
        }),
      ),
    ],
    [records],
  );

  const officeOptions = useMemo(
    () => [
      { value: defaultOffice, label: defaultOffice },
      ...Array.from(new Set(records.map((record) => record.office))).map(
        (office) => ({
          value: office,
          label: office,
        }),
      ),
    ],
    [records],
  );

  const ratingOptions = useMemo(
    () => [
      { value: defaultRating, label: defaultRating },
      ...["Critical", "High", "Medium", "Low"].map((rating) => ({
        value: rating,
        label: rating,
      })),
    ],
    [],
  );

  const assessorOptions = useMemo(
    () => [
      { value: defaultAssessor, label: defaultAssessor },
      ...Array.from(new Set(records.map((record) => record.assessor))).map(
        (assessor) => ({
          value: assessor,
          label: assessor,
        }),
      ),
    ],
    [records],
  );

  const filteredRecords = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return records.filter((record) => {
      const matchesSearch =
        normalizedSearch.length === 0 ||
        [
          record.id,
          record.auditCode,
          record.auditableUnit,
          record.category,
          record.office,
          record.assessor,
        ].some((value) => value.toLowerCase().includes(normalizedSearch));

      const matchesCategory =
        categoryFilter === defaultCategory || record.category === categoryFilter;
      const matchesOffice =
        officeFilter === defaultOffice || record.office === officeFilter;
      const matchesRating =
        ratingFilter === defaultRating || record.rating === ratingFilter;
      const matchesAssessor =
        assessorFilter === defaultAssessor || record.assessor === assessorFilter;

      return (
        matchesSearch &&
        matchesCategory &&
        matchesOffice &&
        matchesRating &&
        matchesAssessor
      );
    });
  }, [
    assessorFilter,
    categoryFilter,
    officeFilter,
    ratingFilter,
    records,
    search,
  ]);

  const overview = useMemo(() => getRiskRegisterOverview(records), [records]);

  return (
    <AdminLayout>
      <div className="space-y-5 overflow-x-hidden">
        <div className="flex items-start justify-between gap-4 max-[760px]:flex-col">
          <div>
            <Heading as={2} className="text-slate-950">
              Risk Register
            </Heading>
            <Paragraph className="mt-1 text-slate-600">
              View the risk assessment results and prioritize audit units based on
              risk level.
            </Paragraph>
          </div>

          <Button className="h-10 rounded-xl px-4" type="button" variant="outline">
            <Download className="h-4 w-4" />
            Export
          </Button>
        </div>

        <RiskRegisterStatCards
          totalUnits={overview.total}
          auditUniverseTotal={auditUniverseRecords.length}
          critical={overview.critical}
          high={overview.high}
          medium={overview.medium}
          low={overview.low}
          averageScore={overview.averageScore}
        />

        <RiskRegisterFilters
          search={search}
          categoryFilter={categoryFilter}
          officeFilter={officeFilter}
          ratingFilter={ratingFilter}
          assessorFilter={assessorFilter}
          categoryOptions={categoryOptions}
          officeOptions={officeOptions}
          ratingOptions={ratingOptions}
          assessorOptions={assessorOptions}
          onSearchChange={setSearch}
          onCategoryChange={setCategoryFilter}
          onOfficeChange={setOfficeFilter}
          onRatingChange={setRatingFilter}
          onAssessorChange={setAssessorFilter}
        />

        <RiskRegisterTable records={filteredRecords} />
      </div>
    </AdminLayout>
  );
}

export default AdminRiskRegisterPage;
