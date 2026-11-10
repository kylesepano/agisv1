import { useState } from "react";
import { ArrowLeft } from "lucide-react";
import { AdminLayout } from "../../../../layouts/admin/AdminLayout";
import { Button } from "../../../../components/ui/button";
import { Heading, Paragraph } from "../../../../components/ui/typography";
import { navigateTo } from "../../../../routes";
import {
  assessmentMatrixDefaultScores,
} from "../constants";
import { getAssessmentMatrixDefaults } from "../service";
import {
  AssessmentMatrixForm,
  AssessmentSummaryCard,
  RiskCriteriaScore,
} from "../components";
import type {
  AssessmentMatrixScores,
  AssessmentMatrixValues,
} from "../risk-assessment-types";
import {
  calculateAssessmentMatrixTotal,
  getAssessmentMatrixRating,
  getAssessmentPriority,
} from "../risk-assessment-utils";

export function AdminAssessmentMatrixPage() {
  const [formValues, setFormValues] = useState<AssessmentMatrixValues>(
    getAssessmentMatrixDefaults(),
  );
  const [scores, setScores] = useState<AssessmentMatrixScores>(
    assessmentMatrixDefaultScores,
  );

  const totalScore = calculateAssessmentMatrixTotal(scores);
  const rating = getAssessmentMatrixRating(totalScore);
  const priority = getAssessmentPriority(rating);

  return (
    <AdminLayout>
      <div className="w-full space-y-3 overflow-x-hidden">
        <div className="flex items-start justify-between gap-4 max-[760px]:flex-col">
          <div>
            <Heading as={2} className="text-slate-950">
              Assessment Matrix
            </Heading>
            <Paragraph className="mt-0.5 text-slate-600">
              Assess the risk level of the selected audit unit by scoring each criterion from 1 to 5.
            </Paragraph>
          </div>
          <Button
            className="h-9 rounded-lg px-4"
            type="button"
            variant="outline"
            onClick={() => navigateTo("/admin/risk-assessment/risk-register")}
          >
            <ArrowLeft size={15} />
            Back to Risk Register
          </Button>
        </div>

        <div className="grid w-full items-start gap-3 xl:grid-cols-[minmax(280px,0.95fr)_minmax(0,2.45fr)_minmax(340px,1.15fr)]">
          <AssessmentMatrixForm
            values={formValues}
            onChange={(key, value) =>
              setFormValues((current) => ({ ...current, [key]: value }))
            }
          />
          <RiskCriteriaScore
            scores={scores}
            remarks={formValues.remarks}
            onScoreChange={(key, value) =>
              setScores((current) => ({ ...current, [key]: value }))
            }
            onRemarksChange={(value) =>
              setFormValues((current) => ({ ...current, remarks: value }))
            }
          />
          <AssessmentSummaryCard
            totalScore={totalScore}
            rating={rating}
            priority={priority}
            onReset={() => {
              setFormValues(getAssessmentMatrixDefaults());
              setScores(assessmentMatrixDefaultScores);
            }}
            onSave={() => undefined}
          />
        </div>
      </div>
    </AdminLayout>
  );
}

export default AdminAssessmentMatrixPage;
