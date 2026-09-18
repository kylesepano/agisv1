export function milestoneWeightState(milestones) {
  const supplied = milestones.filter(
    (milestone) =>
      milestone.weightPercentage !== "" &&
      milestone.weightPercentage !== null &&
      milestone.weightPercentage !== undefined,
  );
  const total = supplied.reduce(
    (sum, milestone) => sum + Number(milestone.weightPercentage || 0),
    0,
  );

  return {
    supplied: supplied.length,
    total,
    partial: supplied.length > 0 && supplied.length !== milestones.length,
    valid:
      supplied.length === 0 ||
      (supplied.length === milestones.length && Math.abs(total - 100) < 0.001),
  };
}
