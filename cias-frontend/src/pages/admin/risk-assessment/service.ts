import {
  assessmentSummaryMetrics,
  defaultAssessmentMatrixValues,
  riskRegisterRecords,
} from "./risk-assessment-data";

export function getAssessmentMatrixDefaults() {
  return defaultAssessmentMatrixValues;
}

export function getRiskRegisterRecords() {
  return riskRegisterRecords;
}

export function getAssessmentSummaryMetrics() {
  return assessmentSummaryMetrics;
}
